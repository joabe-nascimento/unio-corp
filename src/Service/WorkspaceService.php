<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Organismo\OrganismoFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolve a colônia/empresa ativa (sem seleção manual de workspace).
 */
class WorkspaceService
{
    private const MEMO_ATTR = '_unio_workspace_memo';

    /** CNPJ placeholder da colônia padrão (organismo). */
    private const DEFAULT_COLONIA_CNPJ = '00.000.000/0001-91';

    public function __construct(
        private RequestStack $requestStack,
        private EmpresaRepository $empresaRepo,
        private OrganismoFeature $organismo,
        private OrganismoCopyService $copy,
        private EntityManagerInterface $em,
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

        $empresa = $this->empresaRepo->findOneBy(['ativo' => true], ['id' => 'ASC'])
            ?? $this->empresaRepo->findOneBy([], ['id' => 'ASC']);

        if ($empresa instanceof Empresa) {
            return $empresa;
        }

        if (!$this->organismo->isEnabled()) {
            return null;
        }

        return $this->provisionDefaultColonia();
    }

    private function provisionDefaultColonia(): Empresa
    {
        $existing = $this->empresaRepo->findOneBy(['cnpj' => self::DEFAULT_COLONIA_CNPJ]);
        if ($existing instanceof Empresa) {
            return $existing;
        }

        $nome = trim($this->copy->brandName());
        if ($nome === '') {
            $nome = 'Unio';
        }

        $empresa = (new Empresa())
            ->setNome($nome)
            ->setCnpj(self::DEFAULT_COLONIA_CNPJ)
            ->setSetor($this->copy->colonia())
            ->setAtivo(true);

        $this->em->persist($empresa);
        $this->em->flush();

        return $empresa;
    }
}
