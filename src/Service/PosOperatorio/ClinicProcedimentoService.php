<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicProcedimento;
use App\Entity\Empresa;
use App\Repository\ClinicProcedimentoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicProcedimentoService
{
    public function __construct(
        private ClinicProcedimentoRepository $procedimentos,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicProcedimento> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->procedimentos->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicProcedimento
    {
        return $this->procedimentos->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): ClinicProcedimento
    {
        $procedimento = new ClinicProcedimento();
        $procedimento->setEmpresa($empresa);
        $this->apply($procedimento, $empresa, $data, true);
        $this->em->persist($procedimento);
        $this->em->flush();

        return $procedimento;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ClinicProcedimento $procedimento, Empresa $empresa, array $data): ClinicProcedimento
    {
        $this->assertScope($procedimento, $empresa);
        $this->apply($procedimento, $empresa, $data, false);
        $procedimento->touch();
        $this->em->flush();

        return $procedimento;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicProcedimento $procedimento, Empresa $empresa, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('nome', $data)) {
            $procedimento->setNome(ClinicCadastroRules::requireNome((string) ($data['nome'] ?? ''), 180));
        }
        if ($creating || \array_key_exists('codigo_interno', $data)) {
            $codigo = trim((string) ($data['codigo_interno'] ?? ''));
            $codigo = $codigo === '' ? null : mb_substr($codigo, 0, 32);
            if ($codigo !== null) {
                $this->assertCodigoInternoUnique($empresa, $codigo, $procedimento->getId());
            }
            $procedimento->setCodigoInterno($codigo);
        }
        if ($creating || \array_key_exists('codigo_tuss', $data)) {
            $tuss = trim((string) ($data['codigo_tuss'] ?? ''));
            $procedimento->setCodigoTuss($tuss === '' ? null : mb_substr($tuss, 0, 20));
        }
        if ($creating || \array_key_exists('valor', $data) || \array_key_exists('valor_centavos', $data)) {
            if (\array_key_exists('valor_centavos', $data) && $data['valor_centavos'] !== null && $data['valor_centavos'] !== '') {
                $procedimento->setValorCentavos((int) $data['valor_centavos']);
            } else {
                $procedimento->setValorCentavos(ClinicCadastroRules::parseMoneyToCentavos(
                    isset($data['valor']) ? (string) $data['valor'] : null
                ));
            }
        }
        if ($creating || \array_key_exists('duracao_minutos', $data)) {
            $duracao = (int) ($data['duracao_minutos'] ?? 30);
            $procedimento->setDuracaoMinutos(ClinicCadastroRules::assertDuracaoMinutos($duracao > 0 ? $duracao : 30));
        }
        if ($creating || \array_key_exists('descricao', $data)) {
            $desc = trim((string) ($data['descricao'] ?? ''));
            $procedimento->setDescricao($desc === '' ? null : $desc);
        }
        if ($creating || \array_key_exists('ativo', $data)) {
            $procedimento->setAtivo(($data['ativo'] ?? true) !== false);
        }
    }

    private function assertCodigoInternoUnique(Empresa $empresa, string $codigo, ?int $ignoreId): void
    {
        $existing = $this->procedimentos->findOneBy(['empresa' => $empresa, 'codigoInterno' => $codigo]);
        if ($existing !== null && $existing->getId() !== $ignoreId) {
            throw new \InvalidArgumentException('Já existe procedimento com este código interno.');
        }
    }

    private function assertScope(ClinicProcedimento $procedimento, Empresa $empresa): void
    {
        if ($procedimento->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Procedimento fora do escopo.');
        }
    }
}
