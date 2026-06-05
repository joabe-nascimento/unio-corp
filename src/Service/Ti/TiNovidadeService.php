<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiNovidade;
use App\Entity\User;
use App\Repository\TiNovidadeRepository;

final class TiNovidadeService
{
    public function __construct(
        private TiNovidadeRepository $repo,
        private TiNovidadeSeedService $seed,
        private TiNovidadeManageService $manage,
    ) {}

    public function ensureInitialized(Empresa $empresa): void
    {
        $this->seed->seedIfEmpty($empresa);
    }

    /** @return list<array<string, mixed>> */
    public function feed(Empresa $empresa, int $limit = 50): array
    {
        $this->ensureInitialized($empresa);

        return array_map(
            static fn (TiNovidade $n) => $n->toArray(),
            $this->repo->findByEmpresa($empresa, $limit),
        );
    }

    /** @return list<array<string, mixed>> */
    public function feedSlice(Empresa $empresa, int $limit = 3): array
    {
        return \array_slice($this->feed($empresa, $limit), 0, $limit);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): TiNovidade
    {
        return $this->manage->loadForEmpresa($empresa, $id);
    }

    /** @param array<string, mixed> $data */
    public function createFromForm(Empresa $empresa, User $autor, array $data): TiNovidade
    {
        return $this->manage->createFromForm($empresa, $autor, $data);
    }

    /** @param array<string, mixed> $data */
    public function updateFromForm(TiNovidade $novidade, array $data): void
    {
        $this->manage->updateFromForm($novidade, $data);
    }

    public function delete(TiNovidade $novidade): void
    {
        $this->manage->delete($novidade);
    }
}
