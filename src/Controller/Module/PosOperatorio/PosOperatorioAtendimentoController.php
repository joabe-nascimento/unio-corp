<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicAtendimentoService;
use App\Service\PosOperatorio\ClinicSoapTemplateService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/atendimento')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioAtendimentoController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicAtendimentoService $atendimentos,
        private ?ClinicSoapTemplateService $soapTemplates = null,
    ) {}

    #[Route('', name: 'app_pos_operatorio_atendimento', methods: ['GET'])]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render('modules/pos-operatorio/atendimento/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'atendimento',
            'atendimentos' => $this->atendimentos->listRecent($empresa),
            'status_labels' => ClinicAtendimentoService::statusLabels(),
        ]);
    }

    #[Route('/agenda/{agendamentoId}', name: 'app_pos_operatorio_atendimento_abrir', requirements: ['agendamentoId' => '\d+'], methods: ['POST'])]
    public function abrir(int $agendamentoId, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('clinic_atendimento_abrir_'.$agendamentoId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_agenda');
        }

        try {
            $agendamento = $this->atendimentos->requireAgendamento($empresa, $agendamentoId);
            $atendimento = $this->atendimentos->startFromAgendamento($empresa, $agendamento, $user);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_pos_operatorio_agenda');
        }

        return $this->redirectToRoute('app_pos_operatorio_atendimento_show', ['id' => $atendimento->getId()]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_atendimento_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $atendimento = $this->atendimentos->findForEmpresa($empresa, $id);
        if ($atendimento === null) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_atendimento_'.$id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_atendimento_show', ['id' => $id]);
            }

            $data = [
                'queixa' => $request->request->getString('queixa'),
                'exame' => $request->request->getString('exame'),
                'conduta' => $request->request->getString('conduta'),
                'observacao' => $request->request->getString('observacao'),
                'hipotese' => $request->request->getString('hipotese'),
                'cid10' => $request->request->getString('cid10'),
            ];
            $action = $request->request->getString('action', 'salvar');

            try {
                if ($action === 'finalizar') {
                    $this->atendimentos->finalize($atendimento, $empresa, $user, $data);
                    $this->addFlash('success', 'Atendimento finalizado. Conta particular aberta.');

                    return $this->redirectToRoute('app_pos_operatorio_contas', ['status' => 'aberto']);
                }

                $this->atendimentos->saveDraft($atendimento, $empresa, $data);
                $this->addFlash('success', 'Atendimento salvo.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_atendimento_show', ['id' => $id]);
        }

        return $this->render('modules/pos-operatorio/atendimento/show.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'atendimento',
            'atendimento' => $atendimento,
            'agendamento' => $atendimento->getAgendamento(),
            'status_labels' => ClinicAtendimentoService::statusLabels(),
            'soap_templates' => $this->soapTemplates?->list($empresa, true) ?? [],
            'cid10_search_url' => $this->generateUrl('app_pos_operatorio_cid10_search'),
        ]);
    }

    private function requireEmpresa(): Empresa
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
