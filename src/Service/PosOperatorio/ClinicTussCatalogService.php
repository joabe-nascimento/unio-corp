<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicTussCodigo;
use App\Repository\ClinicTussCodigoRepository;

final class ClinicTussCatalogService
{
    public function __construct(
        private ClinicTussCodigoRepository $codigos,
    ) {}

    /**
     * @return list<array{codigo: string, descricao: string, tabela: string, valor_sugerido: ?float, valor_sugerido_centavos: ?int}>
     */
    public function search(string $q, int $limit = 20): array
    {
        return array_map(
            static fn (ClinicTussCodigo $c): array => [
                'codigo' => $c->getCodigo(),
                'descricao' => $c->getDescricao(),
                'tabela' => $c->getTabela(),
                'valor_sugerido_centavos' => $c->getValorSugeridoCentavos(),
                'valor_sugerido' => $c->getValorSugeridoCentavos() !== null
                    ? round($c->getValorSugeridoCentavos() / 100, 2)
                    : null,
            ],
            $this->codigos->search($q, $limit),
        );
    }

    public function findByCodigo(string $codigo): ?ClinicTussCodigo
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        return $this->codigos->findOneByCodigo($codigo);
    }
}
