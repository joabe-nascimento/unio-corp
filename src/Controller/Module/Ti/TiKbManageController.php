<?php

namespace App\Controller\Module\Ti;

use App\Entity\Empresa;
use App\Entity\User;
use App\Security\TiGrantService;
use App\Service\Ti\TiKbService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TiKbManageController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private TiKbService $kb,
        private TiGrantService $tiGrants,
    ) {}

    #[Route('/ti/kb/novo', name: 'app_ti_kb_novo_submit', methods: ['POST'])]
    public function novo(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canManageKb($user));
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_kb_form');
        try {
            $this->kb->create($empresa, $request->request->all());
            $this->addFlash('success', 'Artigo KB criado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_ti_kb', ['open_novo' => 1]);
        }
        return $this->redirectToRoute('app_ti_kb');
    }

    #[Route('/ti/kb/{id}/editar', name: 'app_ti_kb_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canManageKb($user));
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_kb_form');
        try {
            $this->kb->update($this->kb->load($empresa, $id), $request->request->all());
            $this->addFlash('success', 'Artigo atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_ti_kb', ['open_edit' => $id]);
        }
        return $this->redirectToRoute('app_ti_kb');
    }

    #[Route('/ti/kb/{id}/excluir', name: 'app_ti_kb_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->tiGrants->assert($user, $this->tiGrants->canManageKb($user));
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_kb_delete_' . $id);
        $this->kb->delete($this->kb->load($empresa, $id));
        $this->addFlash('success', 'Artigo excluído.');
        return $this->redirectToRoute('app_ti_kb');
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
