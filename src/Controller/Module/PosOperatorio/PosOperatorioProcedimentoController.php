<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicProcedimento;
use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicProcedimentoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/procedimentos')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioProcedimentoController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicProcedimentoService $procedimentos,
    ) {}

    #[Route('', name: 'app_pos_operatorio_procedimentos', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_procedimento_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_procedimentos');
            }

            try {
                $this->procedimentos->create($empresa, $this->payload($request, true));
                $this->addFlash('success', 'Procedimento cadastrado.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_procedimentos');
        }

        $ativo = $request->query->getString('ativo', '');
        $q = trim($request->query->getString('q'));
        $all = $this->procedimentos->list($empresa);
        $procedimentos = array_values(array_filter(
            $all,
            static function (ClinicProcedimento $procedimento) use ($ativo, $q): bool {
                if ($ativo === '1' && !$procedimento->isAtivo()) {
                    return false;
                }
                if ($ativo === '0' && $procedimento->isAtivo()) {
                    return false;
                }
                if ($q !== '') {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $procedimento->getNome(),
                        $procedimento->getCodigoInterno(),
                        $procedimento->getCodigoTuss(),
                    ])));
                    if (!str_contains($haystack, mb_strtolower($q))) {
                        return false;
                    }
                }

                return true;
            },
        ));
        $ativos = \count(array_filter($all, static fn (ClinicProcedimento $p) => $p->isAtivo()));

        return $this->render('modules/pos-operatorio/procedimentos/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'procedimentos',
            'procedimentos' => $procedimentos,
            'filter_ativo' => $ativo,
            'filter_q' => $q,
            'procedimento_counts' => [
                'total' => \count($all),
                'ativos' => $ativos,
                'inativos' => \count($all) - $ativos,
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_procedimentos_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $procedimento = $this->procedimentos->findForEmpresa($empresa, $id);
        if ($procedimento === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_procedimento_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_procedimentos');
        }

        try {
            $this->procedimentos->update($procedimento, $empresa, $this->payload($request, false));
            $this->addFlash('success', 'Procedimento atualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_procedimentos');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $creating): array
    {
        $data = [
            'nome' => $request->request->getString('nome'),
            'codigo_interno' => $request->request->getString('codigo_interno'),
            'codigo_tuss' => $request->request->getString('codigo_tuss'),
            'valor' => $request->request->getString('valor'),
            'duracao_minutos' => $request->request->getInt('duracao_minutos'),
            'descricao' => $request->request->getString('descricao'),
        ];
        $data['ativo'] = $creating ? true : $request->request->getBoolean('ativo');

        return $data;
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
