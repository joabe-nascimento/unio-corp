<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicUnidade;
use App\Entity\Empresa;
use App\Repository\ClinicUnidadeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicUnidadeService
{
    public function __construct(
        private ClinicUnidadeRepository $unidades,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicUnidade> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->unidades->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicUnidade
    {
        return $this->unidades->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): ClinicUnidade
    {
        $unidade = new ClinicUnidade();
        $unidade->setEmpresa($empresa);
        $this->apply($unidade, $empresa, $data, true);
        $this->em->persist($unidade);
        $this->em->flush();

        return $unidade;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ClinicUnidade $unidade, Empresa $empresa, array $data): ClinicUnidade
    {
        $this->assertScope($unidade, $empresa);
        $this->apply($unidade, $empresa, $data, false);
        $this->em->flush();

        return $unidade;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicUnidade $unidade, Empresa $empresa, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('nome', $data)) {
            $unidade->setNome(ClinicCadastroRules::requireNome((string) ($data['nome'] ?? ''), 120));
        }
        if ($creating || \array_key_exists('codigo', $data)) {
            $codigo = mb_strtoupper(trim((string) ($data['codigo'] ?? '')));
            if ($codigo === '') {
                throw new \InvalidArgumentException('Código da unidade é obrigatório.');
            }
            $codigo = mb_substr($codigo, 0, 16);
            $this->assertCodigoUnique($empresa, $codigo, $unidade->getId());
            $unidade->setCodigo($codigo);
        }
        if ($creating || \array_key_exists('endereco', $data)) {
            $endereco = trim((string) ($data['endereco'] ?? ''));
            $unidade->setEndereco($endereco === '' ? null : mb_substr($endereco, 0, 255));
        }
        if ($creating || \array_key_exists('telefone', $data)) {
            $unidade->setTelefone(ClinicCadastroRules::normalizePhone(
                isset($data['telefone']) ? (string) $data['telefone'] : null
            ));
        }
        if ($creating || \array_key_exists('ativo', $data)) {
            $unidade->setAtivo(($data['ativo'] ?? true) !== false);
        }
    }

    private function assertCodigoUnique(Empresa $empresa, string $codigo, ?int $ignoreId): void
    {
        $existing = $this->unidades->findOneBy(['empresa' => $empresa, 'codigo' => $codigo]);
        if ($existing !== null && $existing->getId() !== $ignoreId) {
            throw new \InvalidArgumentException('Já existe unidade com este código.');
        }
    }

    private function assertScope(ClinicUnidade $unidade, Empresa $empresa): void
    {
        if ($unidade->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Unidade fora do escopo.');
        }
    }
}
