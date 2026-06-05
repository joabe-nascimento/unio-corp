<?php

namespace App\Controller\Module\Ti;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\Ti\TiNovidadeService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TiNovidadeManageController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private TiNovidadeService $novidades,
    ) {}

    #[Route('/ti/novidades/novo', name: 'app_ti_novidade_novo_submit', methods: ['POST'])]
    public function novo(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_novidade_form');

        try {
            $this->novidades->createFromForm($empresa, $user, $request->request->all());
            $this->addFlash('success', 'Comunicado publicado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_novidades', ['open_novo' => 'novidade']);
        }

        return $this->redirectToRoute('app_ti_novidades');
    }

    #[Route('/ti/novidades/{id}/editar', name: 'app_ti_novidade_editar_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_novidade_form');

        try {
            $item = $this->novidades->loadForEmpresa($empresa, $id);
            $this->novidades->updateFromForm($item, $request->request->all());
            $this->addFlash('success', 'Comunicado atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_ti_novidades', ['open_edit' => $id, 'resource' => 'novidade']);
        }

        return $this->redirectToRoute('app_ti_novidades');
    }

    #[Route('/ti/novidades/{id}/excluir', name: 'app_ti_novidade_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'ti_novidade_delete_' . $id);
        $this->novidades->delete($this->novidades->loadForEmpresa($empresa, $id));
        $this->addFlash('success', 'Comunicado excluído.');

        return $this->redirectToRoute('app_ti_novidades');
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
