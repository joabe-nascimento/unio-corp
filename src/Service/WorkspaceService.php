<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class WorkspaceService
{
    private const SESSION_KEY = 'workspace_empresa_id';

    public function __construct(
        private RequestStack $requestStack,
        private EmpresaRepository $empresaRepo
    ) {}

    public function getAvailableEmpresas(User $user): array
    {
        if ($user->isTenant()) {
            return $this->empresaRepo->findBy(['ativo' => true], ['nome' => 'ASC']);
        }
        if ($user->getEmpresa()) {
            return [$user->getEmpresa()];
        }
        return [];
    }

    public function getActiveEmpresa(User $user): ?Empresa
    {
        $session = $this->requestStack->getSession();
        $id = $session->get(self::SESSION_KEY);

        if ($id) {
            $empresa = $this->empresaRepo->find($id);
            if ($empresa && $this->canAccess($user, $empresa)) {
                return $empresa;
            }
        }

        $available = $this->getAvailableEmpresas($user);
        if (!empty($available)) {
            $empresa = $available[0];
            $session->set(self::SESSION_KEY, $empresa->getId());
            return $empresa;
        }

        return null;
    }

    public function switchTo(User $user, int $empresaId): bool
    {
        $empresa = $this->empresaRepo->find($empresaId);
        if (!$empresa || !$this->canAccess($user, $empresa)) {
            return false;
        }
        $this->requestStack->getSession()->set(self::SESSION_KEY, $empresaId);
        return true;
    }

    private function canAccess(User $user, Empresa $empresa): bool
    {
        if ($user->isTenant()) return true;
        return $user->getEmpresa()?->getId() === $empresa->getId();
    }
}