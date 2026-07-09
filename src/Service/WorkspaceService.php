<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolve a clínica ativa do usuário (sem seleção/troca de workspace).
 */
class WorkspaceService
{
    private const MEMO_ATTR = '_unio_workspace_memo';

    public function __construct(
        private RequestStack $requestStack,
        private EmpresaRepository $empresaRepo,
    ) {}

    /** @return list<Empresa> */
    public function getAvailableEmpresas(User $user): array
    {
        $empresa = $this->getActiveEmpresa($user);

        return $empresa !== null ? [$empresa] : [];
    }

    public function getActiveEmpresa(User $user): ?Empresa
    {
        return $this->getMemo($user)['empresa'];
    }

    /** @deprecated Sem troca de workspace — no-op de compatibilidade. */
    public function switchTo(User $user, int $empresaId): bool
    {
        $empresa = $this->getActiveEmpresa($user);

        return $empresa !== null && $empresa->getId() === $empresaId;
    }

    /** @return array{empresas: list<Empresa>, empresa: ?Empresa} */
    private function getMemo(User $user): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request?->attributes->has(self::MEMO_ATTR)) {
            return $request->attributes->get(self::MEMO_ATTR);
        }

        $empresa = $this->resolveEmpresa($user);
        $data = [
            'empresas' => $empresa !== null ? [$empresa] : [],
            'empresa' => $empresa,
        ];

        if ($request instanceof Request) {
            $request->attributes->set(self::MEMO_ATTR, $data);
        }

        return $data;
    }

    private function resolveEmpresa(User $user): ?Empresa
    {
        if ($user->getEmpresa() !== null) {
            return $user->getEmpresa();
        }

        return $this->empresaRepo->findOneBy(['ativo' => true], ['id' => 'ASC']);
    }
}
