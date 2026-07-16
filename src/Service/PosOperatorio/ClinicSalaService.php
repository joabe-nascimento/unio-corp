<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicSala;
use App\Entity\ClinicUnidade;
use App\Entity\Empresa;
use App\Repository\ClinicSalaRepository;
use App\Repository\ClinicUnidadeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicSalaService
{
    public function __construct(
        private ClinicSalaRepository $salas,
        private ClinicUnidadeRepository $unidades,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicSala> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->salas->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicSala
    {
        return $this->salas->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): ClinicSala
    {
        $sala = new ClinicSala();
        $sala->setEmpresa($empresa);
        $this->apply($sala, $empresa, $data, true);
        $this->em->persist($sala);
        $this->em->flush();

        return $sala;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ClinicSala $sala, Empresa $empresa, array $data): ClinicSala
    {
        $this->assertScope($sala, $empresa);
        $this->apply($sala, $empresa, $data, false);
        $this->em->flush();

        return $sala;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicSala $sala, Empresa $empresa, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('nome', $data)) {
            $sala->setNome(ClinicCadastroRules::requireNome((string) ($data['nome'] ?? ''), 120));
        }
        if ($creating || \array_key_exists('codigo', $data)) {
            $codigo = mb_strtoupper(trim((string) ($data['codigo'] ?? '')));
            if ($codigo === '') {
                throw new \InvalidArgumentException('Código da sala é obrigatório.');
            }
            $codigo = mb_substr($codigo, 0, 16);
            $this->assertCodigoUnique($empresa, $codigo, $sala->getId());
            $sala->setCodigo($codigo);
        }
        if ($creating || \array_key_exists('tipo', $data)) {
            $tipo = trim((string) ($data['tipo'] ?? ClinicSala::TIPO_CONSULTORIO));
            if (!isset(ClinicCadastroRules::TIPOS_SALA[$tipo])) {
                throw new \InvalidArgumentException('Tipo de sala inválido.');
            }
            $sala->setTipo($tipo);
        }
        if ($creating || \array_key_exists('capacidade', $data)) {
            $capacidade = (int) ($data['capacidade'] ?? 1);
            if ($capacidade < 1 || $capacidade > 20) {
                throw new \InvalidArgumentException('Capacidade deve ser entre 1 e 20.');
            }
            $sala->setCapacidade($capacidade);
        }
        if ($creating || \array_key_exists('unidade_id', $data)) {
            $sala->setUnidade($this->resolveUnidade($empresa, $data['unidade_id'] ?? null));
        }
        if ($creating || \array_key_exists('ativo', $data)) {
            $sala->setAtivo(($data['ativo'] ?? true) !== false);
        }
    }

    private function resolveUnidade(Empresa $empresa, mixed $unidadeId): ?ClinicUnidade
    {
        if ($unidadeId === null || $unidadeId === '' || (int) $unidadeId <= 0) {
            return null;
        }
        $unidade = $this->unidades->findOneByEmpresa($empresa, (int) $unidadeId);
        if ($unidade === null) {
            throw new \InvalidArgumentException('Unidade inválida.');
        }

        return $unidade;
    }

    private function assertCodigoUnique(Empresa $empresa, string $codigo, ?int $ignoreId): void
    {
        $existing = $this->salas->findOneBy(['empresa' => $empresa, 'codigo' => $codigo]);
        if ($existing !== null && $existing->getId() !== $ignoreId) {
            throw new \InvalidArgumentException('Já existe sala com este código.');
        }
    }

    private function assertScope(ClinicSala $sala, Empresa $empresa): void
    {
        if ($sala->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Sala fora do escopo.');
        }
    }
}
