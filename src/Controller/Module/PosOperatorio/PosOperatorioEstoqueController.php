<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicEstoqueItem;
use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicCadastroRules;
use App\Service\PosOperatorio\ClinicEstoqueItemService;
use App\Service\PosOperatorio\ClinicUnidadeService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/estoque')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioEstoqueController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicEstoqueItemService $estoque,
        private ClinicUnidadeService $unidades,
    ) {}

    #[Route('', name: 'app_pos_operatorio_estoque', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_estoque_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_estoque');
            }

            try {
                $result = $this->estoque->create($empresa, $this->payload($request, true));
                $this->addFlash('success', 'Item de estoque cadastrado.');
                if ($result['abaixo_minimo']) {
                    $this->addFlash('warning', 'Atenção: quantidade abaixo do mínimo.');
                }
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_estoque');
        }

        $ativo = $request->query->getString('ativo', '');
        $baixo = $request->query->getString('baixo', '');
        $q = trim($request->query->getString('q'));
        $all = $this->estoque->list($empresa);
        $itens = array_values(array_filter(
            $all,
            static function (ClinicEstoqueItem $item) use ($ativo, $baixo, $q): bool {
                if ($baixo === '1' && !$item->isAbaixoMinimo()) {
                    return false;
                }
                if ($ativo === '1' && !$item->isAtivo()) {
                    return false;
                }
                if ($ativo === '0' && $item->isAtivo()) {
                    return false;
                }
                if ($q !== '') {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $item->getNome(),
                        $item->getSku(),
                        $item->getUnidade()?->getNome(),
                    ])));
                    if (!str_contains($haystack, mb_strtolower($q))) {
                        return false;
                    }
                }

                return true;
            },
        ));
        $ativos = \count(array_filter($all, static fn (ClinicEstoqueItem $i) => $i->isAtivo()));
        $unidadesMedida = ClinicCadastroRules::UNIDADES_MEDIDA_ESTOQUE;
        foreach ($all as $item) {
            $um = trim($item->getUnidadeMedida());
            if ($um !== '' && !isset($unidadesMedida[$um])) {
                $unidadesMedida[$um] = $um;
            }
        }

        return $this->render('modules/pos-operatorio/estoque/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'estoque',
            'itens' => $itens,
            'unidades' => $this->unidades->list($empresa, true),
            'unidades_medida' => $unidadesMedida,
            'filter_ativo' => $ativo,
            'filter_baixo' => $baixo,
            'filter_q' => $q,
            'estoque_counts' => [
                'total' => \count($all),
                'ativos' => $ativos,
                'inativos' => \count($all) - $ativos,
                'baixo' => \count(array_filter($all, static fn (ClinicEstoqueItem $i) => $i->isAbaixoMinimo())),
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_estoque_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->estoque->findForEmpresa($empresa, $id);
        if ($item === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_estoque_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_estoque');
        }

        try {
            $result = $this->estoque->update($item, $empresa, $this->payload($request, false));
            $this->addFlash('success', 'Item de estoque atualizado.');
            if ($result['abaixo_minimo']) {
                $this->addFlash('warning', 'Atenção: quantidade abaixo do mínimo.');
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_estoque');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $creating): array
    {
        return [
            'nome' => $request->request->getString('nome'),
            'sku' => $request->request->getString('sku'),
            'unidade_medida' => $request->request->getString('unidade_medida'),
            'quantidade' => $request->request->getInt('quantidade'),
            'minimo' => $request->request->getInt('minimo'),
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
