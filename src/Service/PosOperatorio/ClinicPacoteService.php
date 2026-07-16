<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicPacote;
use App\Entity\Empresa;
use App\Repository\ClinicPacoteRepository;
use App\Repository\ClinicProcedimentoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicPacoteService
{
    public function __construct(
        private ClinicPacoteRepository $pacotes,
        private ClinicProcedimentoRepository $procedimentos,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicPacote> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->pacotes->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicPacote
    {
        return $this->pacotes->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): ClinicPacote
    {
        $pacote = new ClinicPacote();
        $pacote->setEmpresa($empresa);
        $this->apply($pacote, $empresa, $data, true);
        $this->em->persist($pacote);
        $this->em->flush();

        return $pacote;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ClinicPacote $pacote, Empresa $empresa, array $data): ClinicPacote
    {
        $this->assertScope($pacote, $empresa);
        $this->apply($pacote, $empresa, $data, false);
        $this->em->flush();

        return $pacote;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicPacote $pacote, Empresa $empresa, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('nome', $data)) {
            $pacote->setNome(ClinicCadastroRules::requireNome((string) ($data['nome'] ?? ''), 160));
        }
        if ($creating || \array_key_exists('descricao', $data)) {
            $desc = trim((string) ($data['descricao'] ?? ''));
            $pacote->setDescricao($desc === '' ? null : $desc);
        }
        if ($creating || \array_key_exists('itens', $data)) {
            $itens = $this->normalizeItens($empresa, $data['itens'] ?? []);
            $pacote->setItens($itens);
            if (!\array_key_exists('valor', $data) && !\array_key_exists('valor_centavos', $data)) {
                $pacote->setValorCentavos($this->sumItens($itens));
            }
        }
        if ($creating || \array_key_exists('valor', $data) || \array_key_exists('valor_centavos', $data)) {
            if (\array_key_exists('valor_centavos', $data) && $data['valor_centavos'] !== null && $data['valor_centavos'] !== '') {
                $pacote->setValorCentavos((int) $data['valor_centavos']);
            } else {
                $pacote->setValorCentavos(ClinicCadastroRules::parseMoneyToCentavos(
                    isset($data['valor']) ? (string) $data['valor'] : null
                ));
            }
        }
        if ($creating || \array_key_exists('ativo', $data)) {
            $pacote->setAtivo(($data['ativo'] ?? true) !== false);
        }
    }

    /**
     * @param mixed $raw
     *
     * @return list<array{procedimento_id: ?int, nome: string, valor_centavos: int}>
     */
    private function normalizeItens(Empresa $empresa, mixed $raw): array
    {
        if (\is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!\is_array($decoded)) {
                throw new \InvalidArgumentException('Itens do pacote devem ser JSON válido.');
            }
            $raw = $decoded;
        }
        if (!\is_array($raw)) {
            return [];
        }

        $itens = [];
        foreach ($raw as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $nome = trim((string) ($row['nome'] ?? ''));
            $procedimentoId = isset($row['procedimento_id']) && $row['procedimento_id'] !== ''
                ? (int) $row['procedimento_id']
                : null;

            if ($procedimentoId !== null && $procedimentoId > 0) {
                $proc = $this->procedimentos->findOneByEmpresa($empresa, $procedimentoId);
                if ($proc === null) {
                    throw new \InvalidArgumentException('Procedimento do pacote inválido.');
                }
                if ($nome === '') {
                    $nome = $proc->getNome();
                }
                $valor = isset($row['valor_centavos'])
                    ? (int) $row['valor_centavos']
                    : ($proc->getValorCentavos() ?? ClinicCadastroRules::parseMoneyToCentavos(isset($row['valor']) ? (string) $row['valor'] : null) ?? 0);
            } else {
                if ($nome === '') {
                    throw new \InvalidArgumentException('Item do pacote exige nome.');
                }
                if (isset($row['valor_centavos']) && $row['valor_centavos'] !== '') {
                    $valor = (int) $row['valor_centavos'];
                } else {
                    $valor = ClinicCadastroRules::parseMoneyToCentavos(isset($row['valor']) ? (string) $row['valor'] : null) ?? 0;
                }
                $procedimentoId = null;
            }

            $itens[] = [
                'procedimento_id' => $procedimentoId,
                'nome' => mb_substr($nome, 0, 180),
                'valor_centavos' => max(0, $valor),
            ];
        }

        return $itens;
    }

    /**
     * @param list<array{valor_centavos: int}> $itens
     */
    private function sumItens(array $itens): int
    {
        $total = 0;
        foreach ($itens as $item) {
            $total += (int) ($item['valor_centavos'] ?? 0);
        }

        return $total;
    }

    private function assertScope(ClinicPacote $pacote, Empresa $empresa): void
    {
        if ($pacote->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Pacote fora do escopo.');
        }
    }
}
