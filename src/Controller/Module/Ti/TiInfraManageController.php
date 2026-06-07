<?php

namespace App\Controller\Module\Ti;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\TiGrantService;
use App\Service\Ti\TiCatalogoService;
use App\Service\Ti\TiInfraManageService;
use App\Service\Ti\TiNotificationService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TiInfraManageController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private TiInfraManageService $infra,
        private TiNotificationService $notifications,
        private UserRepository $userRepository,
        private TiCatalogoService $catalogo,
        private TiGrantService $tiGrants,
    ) {}

    // ── Ativos ───────────────────────────────────────────────────────────────

    #[Route('/ti/ativos/novo', name: 'app_ti_ativo_novo_submit', methods: ['POST'])]
    public function ativoNovo(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'ativos');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_ativo_form');

        try {
            $this->infra->createAtivo($empresa, $request->request->all());
            $this->addFlash('success', 'Ativo cadastrado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_ativos', ['open_novo' => 'ativo']);
        }

        return $this->redirectToRoute('app_ti_ativos');
    }

    #[Route('/ti/ativos/{id}/editar', name: 'app_ti_ativo_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function ativoEditar(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'ativos');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_ativo_form');

        try {
            $ativo = $this->infra->loadAtivo($empresa, $id);
            $this->infra->updateAtivo($ativo, $request->request->all());
            $this->addFlash('success', 'Ativo atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_ativos', ['open_edit' => $id, 'resource' => 'ativo']);
        }

        return $this->redirectToRoute('app_ti_ativos');
    }

    #[Route('/ti/ativos/{id}/excluir', name: 'app_ti_ativo_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function ativoExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'ativos');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_ativo_delete_' . $id);
        $this->infra->deleteAtivo($this->infra->loadAtivo($empresa, $id));
        $this->addFlash('success', 'Ativo excluído.');

        return $this->redirectToRoute('app_ti_ativos');
    }

    // ── Licenças ─────────────────────────────────────────────────────────────

    #[Route('/ti/licencas/novo', name: 'app_ti_licenca_novo_submit', methods: ['POST'])]
    public function licencaNovo(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'licencas');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_licenca_form');

        try {
            $this->infra->createLicenca($empresa, $request->request->all());
            $this->addFlash('success', 'Licença cadastrada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_licencas', ['open_novo' => 'licenca']);
        }

        return $this->redirectToRoute('app_ti_licencas');
    }

    #[Route('/ti/licencas/{id}/editar', name: 'app_ti_licenca_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function licencaEditar(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'licencas');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_licenca_form');

        try {
            $lic = $this->infra->loadLicenca($empresa, $id);
            $this->infra->updateLicenca($lic, $request->request->all());
            $this->addFlash('success', 'Licença atualizada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_licencas', ['open_edit' => $id, 'resource' => 'licenca']);
        }

        return $this->redirectToRoute('app_ti_licencas');
    }

    #[Route('/ti/licencas/{id}/excluir', name: 'app_ti_licenca_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function licencaExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'licencas');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_licenca_delete_' . $id);
        $this->infra->deleteLicenca($this->infra->loadLicenca($empresa, $id));
        $this->addFlash('success', 'Licença excluída.');

        return $this->redirectToRoute('app_ti_licencas');
    }

    // ── Integrações ──────────────────────────────────────────────────────────

    #[Route('/ti/integracoes/novo', name: 'app_ti_integracao_novo_submit', methods: ['POST'])]
    public function integracaoNovo(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'integracoes');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_integracao_form');

        try {
            $this->infra->createIntegracao($empresa, $request->request->all());
            $this->addFlash('success', 'Integração cadastrada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_integracoes', ['open_novo' => 'integracao']);
        }

        return $this->redirectToRoute('app_ti_integracoes');
    }

    #[Route('/ti/integracoes/{id}/editar', name: 'app_ti_integracao_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function integracaoEditar(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'integracoes');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_integracao_form');

        try {
            $int = $this->infra->loadIntegracao($empresa, $id);
            $this->infra->updateIntegracao($int, $request->request->all());
            $this->addFlash('success', 'Integração atualizada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_integracoes', ['open_edit' => $id, 'resource' => 'integracao']);
        }

        return $this->redirectToRoute('app_ti_integracoes');
    }

    #[Route('/ti/integracoes/{id}/excluir', name: 'app_ti_integracao_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function integracaoExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'integracoes');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_integracao_delete_' . $id);
        $this->infra->deleteIntegracao($this->infra->loadIntegracao($empresa, $id));
        $this->addFlash('success', 'Integração excluída.');

        return $this->redirectToRoute('app_ti_integracoes');
    }

    // ── Manutenções ──────────────────────────────────────────────────────────

    #[Route('/ti/manutencoes/novo', name: 'app_ti_manutencao_novo_submit', methods: ['POST'])]
    public function manutencaoNovo(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'manutencoes');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_manutencao_form');

        try {
            $this->infra->createManutencao($empresa, $request->request->all());
            $this->addFlash('success', 'Manutenção cadastrada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_manutencoes', ['open_novo' => 'manutencao']);
        }

        return $this->redirectToRoute('app_ti_manutencoes');
    }

    #[Route('/ti/manutencoes/{id}/editar', name: 'app_ti_manutencao_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function manutencaoEditar(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'manutencoes');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_manutencao_form');

        try {
            $man = $this->infra->loadManutencao($empresa, $id);
            $this->infra->updateManutencao($man, $request->request->all());
            $this->addFlash('success', 'Manutenção atualizada.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_manutencoes', ['open_edit' => $id, 'resource' => 'manutencao']);
        }

        return $this->redirectToRoute('app_ti_manutencoes');
    }

    #[Route('/ti/manutencoes/{id}/excluir', name: 'app_ti_manutencao_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function manutencaoExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertInfraManage($user, 'manutencoes');
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_manutencao_delete_' . $id);
        $this->infra->deleteManutencao($this->infra->loadManutencao($empresa, $id));
        $this->addFlash('success', 'Manutenção excluída.');

        return $this->redirectToRoute('app_ti_manutencoes');
    }

    #[Route('/ti/manutencoes/{id}/aprovar', name: 'app_ti_manutencao_aprovar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function aprovarManutencao(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canApproveManutencao($user));
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_manutencao_aprovar_' . $id);

        $actorName = $user->getNome() ?: $user->getEmail() ?: 'Gestor';

        $man = $this->infra->loadManutencao($empresa, $id);
        $this->infra->approveManutencao($man, $actorName);

        foreach ($this->userRepository->findActiveByEmpresa($empresa) as $user) {
            $this->notifications->notify(
                $empresa,
                $user,
                'manutencao_aprovada',
                'Manutenção aprovada: ' . $man->getTitulo(),
                'Janela aprovada por ' . $actorName . ' · ' . $man->getJanela(),
                '/ti/manutencoes',
            );
        }

        $this->addFlash('success', 'Manutenção aprovada e equipe notificada.');

        return $this->redirectToRoute('app_ti_manutencoes');
    }

    // ── Catálogo ─────────────────────────────────────────────────────────────

    #[Route('/ti/catalogo/novo', name: 'app_ti_catalogo_novo', methods: ['POST'])]
    public function catalogoNovo(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canManageCatalog($user));
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_catalogo_form');

        try {
            $this->catalogo->create($empresa, $request->request->all());
            $this->addFlash('success', 'Item do catálogo criado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_ti_catalogo');
    }

    #[Route('/ti/catalogo/{id}/editar', name: 'app_ti_catalogo_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function catalogoEditar(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canManageCatalog($user));
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_catalogo_form');

        try {
            $item = $this->catalogo->load($empresa, $id);
            $this->catalogo->update($item, $request->request->all());
            $this->addFlash('success', 'Item atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_ti_catalogo');
    }

    #[Route('/ti/catalogo/{id}/excluir', name: 'app_ti_catalogo_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function catalogoExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canManageCatalog($user));
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_catalogo_delete_' . $id);

        try {
            $item = $this->catalogo->load($empresa, $id);
            $this->catalogo->delete($item);
            $this->addFlash('success', 'Item excluído.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_ti_catalogo');
    }

    private function assertInfraManage(User $user, string $product): void
    {
        $this->tiGrants->assert($user, $this->tiGrants->canManageInfra($user, $product));
    }

    private function requireEmpresa(): Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw new \RuntimeException('Selecione uma área de trabalho.');
        }

        return $empresa;
    }

    private function requireCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }
    }
}
