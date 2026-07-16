<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicSala;
use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicCadastroRules;
use App\Service\PosOperatorio\ClinicSalaService;
use App\Service\PosOperatorio\ClinicUnidadeService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/salas')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioSalaController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicSalaService $salas,
        private ClinicUnidadeService $unidades,
    ) {}

    #[Route('', name: 'app_pos_operatorio_salas', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_sala_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_salas');
            }

            try {
                $this->salas->create($empresa, $this->payload($request, true));
                $this->addFlash('success', 'Sala cadastrada.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_salas');
        }

        $ativo = $request->query->getString('ativo', '');
        $q = trim($request->query->getString('q'));
        $all = $this->salas->list($empresa);
        $lista = array_values(array_filter(
            $all,
            static function (ClinicSala $sala) use ($ativo, $q): bool {
                if ($ativo === '1' && !$sala->isAtivo()) {
                    return false;
                }
                if ($ativo === '0' && $sala->isAtivo()) {
                    return false;
                }
                if ($q !== '') {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $sala->getNome(),
                        $sala->getCodigo(),
                        $sala->getTipo(),
                        $sala->getUnidade()?->getNome(),
                    ])));
                    if (!str_contains($haystack, mb_strtolower($q))) {
                        return false;
                    }
                }

                return true;
            },
        ));
        $ativos = \count(array_filter($all, static fn (ClinicSala $s) => $s->isAtivo()));
        $tiposSala = ClinicCadastroRules::TIPOS_SALA;
        foreach ($all as $sala) {
            $tipo = trim((string) $sala->getTipo());
            if ($tipo !== '' && !isset($tiposSala[$tipo])) {
                $tiposSala[$tipo] = $tipo;
            }
        }

        return $this->render('modules/pos-operatorio/salas/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'salas',
            'salas' => $lista,
            'unidades' => $this->unidades->list($empresa, true),
            'tipos_sala' => $tiposSala,
            'filter_ativo' => $ativo,
            'filter_q' => $q,
            'sala_counts' => [
                'total' => \count($all),
                'ativos' => $ativos,
                'inativos' => \count($all) - $ativos,
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_salas_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $sala = $this->salas->findForEmpresa($empresa, $id);
        if ($sala === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_sala_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_salas');
        }

        try {
            $this->salas->update($sala, $empresa, $this->payload($request, false));
            $this->addFlash('success', 'Sala atualizada.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_salas');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $creating): array
    {
        return [
            'nome' => $request->request->getString('nome'),
            'codigo' => $request->request->getString('codigo'),
            'tipo' => $request->request->getString('tipo'),
            'capacidade' => $request->request->getInt('capacidade'),
            'unidade_id' => $request->request->get('unidade_id'),
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
