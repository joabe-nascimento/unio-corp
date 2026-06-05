<?php

namespace App\Controller\Module\Inovacao;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\User;
use App\Service\WorkspaceService;
use Symfony\Component\HttpFoundation\Request;

trait InovacaoEmpresaScopeTrait
{
    private function requireEmpresa(): Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->getWorkspace()->getActiveEmpresa($user);

        if (!$empresa) {
            throw $this->createAccessDeniedException('Selecione uma área de trabalho para acessar o Núcleo Inovação.');
        }

        return $empresa;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        return DateNormalizer::fromFormDate($value);
    }

    private function requireCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token de segurança inválido.');
        }
    }

    abstract protected function getWorkspace(): WorkspaceService;
}
