<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicCadastroRules;
use App\Service\PosOperatorio\ClinicConvenioService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/convenios')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioConvenioController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicConvenioService $convenios,
    ) {}

    #[Route('', name: 'app_pos_operatorio_convenios', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_convenio_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_convenios');
            }

            try {
                $this->convenios->create($empresa, $this->payload($request, true));
                $this->addFlash('success', 'Convênio cadastrado.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_convenios');
        }

        $lista = $this->convenios->list($empresa);
        $versoesTiss = ClinicCadastroRules::versaoTissSelectOptions(true);
        $known = [];
        foreach ($versoesTiss as $opt) {
            $known[(string) $opt['value']] = true;
        }
        foreach ($lista as $convenio) {
            $v = trim((string) $convenio->getVersaoTiss());
            if ($v !== '' && !isset($known[$v])) {
                $versoesTiss[] = ['value' => $v, 'label' => $v.' (legado)'];
                $known[$v] = true;
            }
        }

        return $this->render('modules/pos-operatorio/convenios/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'convenios',
            'convenios' => $lista,
            'versoes_tiss' => $versoesTiss,
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_convenios_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $convenio = $this->convenios->findForEmpresa($empresa, $id);
        if ($convenio === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_convenio_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_convenios');
        }

        try {
            $this->convenios->update($convenio, $empresa, $this->payload($request, false));
            $this->addFlash('success', 'Convênio atualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_convenios');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $creating): array
    {
        return [
            'nome' => $request->request->getString('nome'),
            'registro_ans' => $request->request->getString('registro_ans'),
            'cnpj' => $request->request->getString('cnpj'),
            'codigo_prestador' => $request->request->getString('codigo_prestador'),
            'versao_tiss' => $request->request->getString('versao_tiss'),
            'contato_faturamento' => $request->request->getString('contato_faturamento'),
            'email_faturamento' => $request->request->getString('email_faturamento'),
            'telefone_faturamento' => $request->request->getString('telefone_faturamento'),
            'prazo_glosa_dias' => $request->request->getInt('prazo_glosa_dias') ?: 30,
            'observacoes' => $request->request->getString('observacoes'),
            'ativo' => $creating ? true : $request->request->getBoolean('ativo'),
        ];
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
