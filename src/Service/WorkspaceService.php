<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class WorkspaceService
{
    private const SESSION_KEY = 'workspace_empresa_id';
    private const MEMO_ATTR = '_unio_workspace_memo';

    public function __construct(
        private RequestStack $requestStack,
        private EmpresaRepository $empresaRepo
    ) {}

    public function getAvailableEmpresas(User $user): array
    {
        return $this->getMemo($user)['empresas'];
    }

    public function getActiveEmpresa(User $user): ?Empresa
    {
        return $this->getMemo($user)['empresa'];
    }

    public function switchTo(User $user, int $empresaId): bool
    {
        $empresa = $this->empresaRepo->find($empresaId);
        if (!$empresa || !$this->canAccess($user, $empresa)) {
            return false;
        }
        $this->requestStack->getSession()->set(self::SESSION_KEY, $empresaId);
        $this->clearMemo();

        return true;
    }

    /** @return array{empresas: list<Empresa>, empresa: ?Empresa} */
    private function getMemo(User $user): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request?->attributes->has(self::MEMO_ATTR)) {
            return $request->attributes->get(self::MEMO_ATTR);
        }

        $data = $this->loadWorkspace($user);
        if ($request instanceof Request) {
            $request->attributes->set(self::MEMO_ATTR, $data);
        }

        return $data;
    }

    /** @return array{empresas: list<Empresa>, empresa: ?Empresa} */
    private function loadWorkspace(User $user): array
    {
        $empresas = $this->fetchAvailableEmpresas($user);
        $empresa = $this->resolveActiveEmpresa($user, $empresas);

        return ['empresas' => $empresas, 'empresa' => $empresa];
    }

    /** @return list<Empresa> */
    private function fetchAvailableEmpresas(User $user): array
    {
        if ($user->isTenant()) {
            return $this->empresaRepo->findBy(['ativo' => true], ['nome' => 'ASC']);
        }
        if ($user->getEmpresa()) {
            return [$user->getEmpresa()];
        }

        return [];
    }

    /** @param list<Empresa> $available */
    private function resolveActiveEmpresa(User $user, array $available): ?Empresa
    {
        $request = $this->requestStack->getCurrentRequest();
        $session = null;

        if ($request?->hasSession()) {
            $session = $request->getSession();
        } elseif ($request?->hasPreviousSession()) {
            $session = $request->getSession();
        }

        if ($session !== null) {
            $id = $session->get(self::SESSION_KEY);

            if ($id) {
                $empresa = $this->empresaRepo->find($id);
                if ($empresa && $this->canAccess($user, $empresa)) {
                    return $empresa;
                }
            }
        }

        if (!empty($available)) {
            $empresa = $available[0];
            if ($session !== null) {
                $session->set(self::SESSION_KEY, $empresa->getId());
            }

            return $empresa;
        }

        return null;
    }

    private function clearMemo(): void
    {
        $this->requestStack->getCurrentRequest()?->attributes->remove(self::MEMO_ATTR);
    }

    private function canAccess(User $user, Empresa $empresa): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        return $user->getEmpresa()?->getId() === $empresa->getId();
    }
}
