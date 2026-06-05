<?php

namespace App\Controller\Module\Integracoes;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

trait IntegracoesEmpresaScopeTrait
{
    abstract protected function getWorkspace(): WorkspaceService;

    protected function requireEmpresa(): Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->getWorkspace()->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw $this->createAccessDeniedException('Selecione uma área de trabalho.');
        }

        return $empresa;
    }

    protected function requireCsrf(\Symfony\Component\HttpFoundation\Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token de segurança inválido.');
        }
    }
}
