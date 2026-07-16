<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicGuiaMedicoService;
use App\Service\PosOperatorio\ClinicProductConfigService;
use App\Service\WorkspaceService;
use App\PosOperatorio\ClinicProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/guia-medico')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioGuiaMedicoController extends AbstractController
{
    private const T = 'modules/pos-operatorio/guia/';

    public function __construct(
        private WorkspaceService $workspace,
        private ClinicGuiaMedicoService $guias,
        private ClinicProductConfigService $productConfig,
    ) {}

    #[Route('', name: 'app_pos_operatorio_guia_medico', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa);

        $ativo = $request->query->getString('ativo', '');
        $padrao = $request->query->getString('padrao', '');
        $q = trim($request->query->getString('q'));
        $all = $this->guias->list($empresa);
        $guias = array_values(array_filter(
            $all,
            static function (array $guia) use ($ativo, $padrao, $q): bool {
                $isAtivo = (bool) ($guia['ativo'] ?? true);
                $isPadrao = (bool) ($guia['padrao'] ?? false);
                if ($padrao === '1' && !$isPadrao) {
                    return false;
                }
                if ($ativo === '1' && !$isAtivo) {
                    return false;
                }
                if ($ativo === '0' && $isAtivo) {
                    return false;
                }
                if ($q !== '') {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $guia['nome'] ?? '',
                        $guia['tipo_procedimento'] ?? '',
                        $guia['subtitulo'] ?? '',
                    ])));
                    if (!str_contains($haystack, mb_strtolower($q))) {
                        return false;
                    }
                }

                return true;
            },
        ));
        $ativos = \count(array_filter($all, static fn (array $g): bool => (bool) ($g['ativo'] ?? true)));

        return $this->render(self::T . 'index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'guia_medico',
            'guias' => $guias,
            'filter_ativo' => $ativo,
            'filter_padrao' => $padrao,
            'filter_q' => $q,
            'guia_counts' => [
                'total' => \count($all),
                'ativos' => $ativos,
                'inativos' => \count($all) - $ativos,
                'padrao' => \count(array_filter($all, static fn (array $g): bool => (bool) ($g['padrao'] ?? false))),
            ],
        ]);
    }

    #[Route('/novo', name: 'app_pos_operatorio_guia_medico_novo', methods: ['GET', 'POST'])]
    #[Route('/{id}/editar', name: 'app_pos_operatorio_guia_medico_editar', methods: ['GET', 'POST'])]
    public function form(Request $request, ?string $id = null): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa);

        $guia = $id ? $this->guias->find($empresa, $id) : null;
        if ($id && $guia === null) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $tokenId = $id ?? 'novo';
            if (!$this->isCsrfTokenValid('guia_medico_' . $tokenId, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Sessão expirada.');

                return $this->redirectToRoute($id ? 'app_pos_operatorio_guia_medico_editar' : 'app_pos_operatorio_guia_medico_novo', $id ? ['id' => $id] : []);
            }

            try {
                $saved = $this->guias->save($empresa, $request->request->all(), $id);
                $this->addFlash('success', 'Guia médico salvo.');

                return $this->redirectToRoute('app_pos_operatorio_guia_medico_editar', ['id' => $saved['id']]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(self::T . 'form.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'guia_medico',
            'guia' => $guia,
            'is_edit' => $id !== null,
        ]);
    }

    #[Route('/{id}/excluir', name: 'app_pos_operatorio_guia_medico_excluir', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa);

        if (!$this->isCsrfTokenValid('guia_medico_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessão expirada.');

            return $this->redirectToRoute('app_pos_operatorio_guia_medico');
        }

        $this->guias->delete($empresa, $id);
        $this->addFlash('success', 'Guia removido.');

        return $this->redirectToRoute('app_pos_operatorio_guia_medico');
    }

    private function requireEmpresa(): Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Selecione uma clínica para continuar.');
        }

        return $empresa;
    }

    private function assertProductEnabled(Empresa $empresa): void
    {
        if (!$this->productConfig->isEnabled($empresa, ClinicProductCatalog::GUIA_MEDICO)) {
            throw $this->createAccessDeniedException('Produto desativado. Ative em Produtos da plataforma.');
        }
    }
}
