<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicOrcamento;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Repository\ClinicOrcamentoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicOrcamentoService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        ClinicOrcamento::STATUS_RASCUNHO => [ClinicOrcamento::STATUS_ENVIADO],
        ClinicOrcamento::STATUS_ENVIADO => [ClinicOrcamento::STATUS_APROVADO, ClinicOrcamento::STATUS_RECUSADO],
        ClinicOrcamento::STATUS_APROVADO => [ClinicOrcamento::STATUS_CONVERTIDO],
        ClinicOrcamento::STATUS_RECUSADO => [],
        ClinicOrcamento::STATUS_CONVERTIDO => [],
    ];

    public function __construct(
        private ClinicOrcamentoRepository $orcamentos,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicOrcamento> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->orcamentos->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicOrcamento
    {
        return $this->orcamentos->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): ClinicOrcamento
    {
        $orcamento = new ClinicOrcamento();
        $orcamento->setEmpresa($empresa);
        $orcamento->setStatus(ClinicOrcamento::STATUS_RASCUNHO);
        $this->apply($orcamento, $data, true);
        $this->em->persist($orcamento);
        $this->em->flush();

        return $orcamento;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ClinicOrcamento $orcamento, Empresa $empresa, array $data): ClinicOrcamento
    {
        $this->assertScope($orcamento, $empresa);

        $newStatus = trim((string) ($data['status'] ?? ''));
        if ($newStatus !== '' && $newStatus !== $orcamento->getStatus()) {
            $this->transition($orcamento, $newStatus);
        }

        $this->apply($orcamento, $data, false);
        $orcamento->touch();
        $this->em->flush();

        return $orcamento;
    }

    public function convertToPacienteLink(ClinicOrcamento $orcamento, PosOperatorioPaciente $paciente): ClinicOrcamento
    {
        if ($orcamento->getEmpresa()->getId() !== $paciente->getEmpresa()->getId()) {
            throw new \InvalidArgumentException('Paciente fora do escopo do orçamento.');
        }

        if ($orcamento->getStatus() !== ClinicOrcamento::STATUS_APROVADO
            && $orcamento->getStatus() !== ClinicOrcamento::STATUS_CONVERTIDO) {
            if ($orcamento->getStatus() === ClinicOrcamento::STATUS_ENVIADO) {
                $this->transition($orcamento, ClinicOrcamento::STATUS_APROVADO);
            } elseif ($orcamento->getStatus() === ClinicOrcamento::STATUS_RASCUNHO) {
                throw new \InvalidArgumentException('Envie e aprove o orçamento antes de converter.');
            }
        }

        if ($orcamento->getStatus() !== ClinicOrcamento::STATUS_CONVERTIDO) {
            $this->transition($orcamento, ClinicOrcamento::STATUS_CONVERTIDO);
        }

        $orcamento->setPaciente($paciente);

        if ($orcamento->getLeadNome() && trim($paciente->getNome()) === '') {
            $paciente->setNome($orcamento->getLeadNome());
        }
        if ($orcamento->getLeadTelefone() && $paciente->getTelefoneContato() === null) {
            $paciente->setTelefoneContato($orcamento->getLeadTelefone());
        }
        if ($orcamento->getLeadEmail() && $paciente->getEmailContato() === null) {
            $paciente->setEmailContato($orcamento->getLeadEmail());
        }

        $orcamento->touch();
        $this->em->flush();

        return $orcamento;
    }

    public function transition(ClinicOrcamento $orcamento, string $to): void
    {
        $from = $orcamento->getStatus();
        if ($from === $to) {
            return;
        }
        if (!isset(ClinicCadastroRules::ORCAMENTO_STATUSES[$to])) {
            throw new \InvalidArgumentException('Status de orçamento inválido.');
        }
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (!\in_array($to, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Transição de status inválida: %s → %s.',
                $from,
                $to
            ));
        }
        $orcamento->setStatus($to);
    }

    /** @return list<string> status atual + próximos permitidos */
    public function selectableStatuses(string $current): array
    {
        $next = self::TRANSITIONS[$current] ?? [];

        return array_values(array_unique(array_merge([$current], $next)));
    }

    public function canConvert(string $status): bool
    {
        return \in_array($status, [
            ClinicOrcamento::STATUS_ENVIADO,
            ClinicOrcamento::STATUS_APROVADO,
        ], true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicOrcamento $orcamento, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('lead_nome', $data)) {
            $nome = trim((string) ($data['lead_nome'] ?? ''));
            $orcamento->setLeadNome($nome === '' ? null : mb_substr($nome, 0, 160));
        }
        if ($creating || \array_key_exists('lead_telefone', $data)) {
            $orcamento->setLeadTelefone(ClinicCadastroRules::normalizePhone(
                isset($data['lead_telefone']) ? (string) $data['lead_telefone'] : null
            ));
        }
        if ($creating || \array_key_exists('lead_email', $data)) {
            $email = trim((string) ($data['lead_email'] ?? ''));
            if ($email === '') {
                $orcamento->setLeadEmail(null);
            } else {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('E-mail do lead inválido.');
                }
                $orcamento->setLeadEmail(mb_substr($email, 0, 120));
            }
        }
        if ($creating || \array_key_exists('itens', $data)) {
            $itens = $this->normalizeItens($data['itens'] ?? []);
            $orcamento->setItens($itens);
            $orcamento->setValorCentavos($this->sumItens($itens));
        }
        if ($creating || \array_key_exists('desconto', $data) || \array_key_exists('desconto_centavos', $data)) {
            if (\array_key_exists('desconto_centavos', $data) && $data['desconto_centavos'] !== null && $data['desconto_centavos'] !== '') {
                $orcamento->setDescontoCentavos(max(0, (int) $data['desconto_centavos']));
            } else {
                $orcamento->setDescontoCentavos(max(0, ClinicCadastroRules::parseMoneyToCentavos(
                    isset($data['desconto']) ? (string) $data['desconto'] : null
                ) ?? 0));
            }
        }
        if ($creating || \array_key_exists('validade', $data)) {
            $raw = trim((string) ($data['validade'] ?? ''));
            if ($raw === '') {
                $orcamento->setValidade(null);
            } else {
                $date = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
                if ($date === false) {
                    throw new \InvalidArgumentException('Validade inválida.');
                }
                $orcamento->setValidade($date);
            }
        }
        if ($creating || \array_key_exists('observacoes', $data)) {
            $obs = trim((string) ($data['observacoes'] ?? ''));
            $orcamento->setObservacoes($obs === '' ? null : $obs);
        }

        if ($creating && $orcamento->getLeadNome() === null && $orcamento->getItens() === []) {
            throw new \InvalidArgumentException('Informe o lead ou itens do orçamento.');
        }
    }

    /**
     * @param mixed $raw
     *
     * @return list<array{nome: string, valor_centavos: int}>
     */
    private function normalizeItens(mixed $raw): array
    {
        if (\is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!\is_array($decoded)) {
                // linha a linha: nome|valor
                $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
                $decoded = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = array_map('trim', explode('|', $line, 2));
                    $decoded[] = [
                        'nome' => $parts[0] ?? '',
                        'valor' => $parts[1] ?? '0',
                    ];
                }
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
            if ($nome === '') {
                continue;
            }
            if (isset($row['valor_centavos']) && $row['valor_centavos'] !== '') {
                $valor = (int) $row['valor_centavos'];
            } else {
                $valor = ClinicCadastroRules::parseMoneyToCentavos(isset($row['valor']) ? (string) $row['valor'] : null) ?? 0;
            }
            $itens[] = [
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

    private function assertScope(ClinicOrcamento $orcamento, Empresa $empresa): void
    {
        if ($orcamento->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Orçamento fora do escopo.');
        }
    }
}
