<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicConta;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ClinicContaRepository;

/**
 * Relatório leve: DRE simplificado + repasse por médico (contas pagas).
 */
final class ClinicFinanceReportService
{
    public function __construct(
        private ClinicContaRepository $contas,
    ) {}

    /**
     * @return array{
     *   dre: array{
     *     a_receber_centavos: int,
     *     recebido_centavos: int,
     *     glosado_centavos: int,
     *     cancelado_centavos: int,
     *     particular_pago_centavos: int,
     *     convenio_pago_centavos: int,
     *     cortesia_centavos: int,
     *     qtd_abertas: int,
     *     qtd_pagas: int
     *   },
     *   repasse: list<array{
     *     medico_id: ?int,
     *     medico_nome: string,
     *     qtd: int,
     *     total_centavos: int,
     *     particular_centavos: int,
     *     convenio_centavos: int
     *   }>
     * }
     */
    public function build(Empresa $empresa): array
    {
        $todas = $this->contas->findByEmpresaAndStatus($empresa, null, 500);

        $dre = [
            'a_receber_centavos' => 0,
            'recebido_centavos' => 0,
            'glosado_centavos' => 0,
            'cancelado_centavos' => 0,
            'particular_pago_centavos' => 0,
            'convenio_pago_centavos' => 0,
            'cortesia_centavos' => 0,
            'qtd_abertas' => 0,
            'qtd_pagas' => 0,
        ];

        /** @var array<string, array{medico_id: ?int, medico_nome: string, qtd: int, total_centavos: int, particular_centavos: int, convenio_centavos: int}> $byMedico */
        $byMedico = [];

        foreach ($todas as $conta) {
            $valor = (int) ($conta->getValorCentavos() ?? 0);
            $status = $conta->getStatus();
            $tipo = $conta->getTipo();

            if ($status === ClinicConta::STATUS_ABERTO) {
                $dre['a_receber_centavos'] += $valor;
                ++$dre['qtd_abertas'];
                continue;
            }

            if ($status === ClinicConta::STATUS_GLOSADO) {
                $dre['glosado_centavos'] += $valor;
                continue;
            }

            if ($status === ClinicConta::STATUS_CANCELADO) {
                $dre['cancelado_centavos'] += $valor;
                continue;
            }

            if ($status !== ClinicConta::STATUS_PAGO) {
                continue;
            }

            $dre['recebido_centavos'] += $valor;
            ++$dre['qtd_pagas'];

            if ($tipo === ClinicConta::TIPO_PARTICULAR) {
                $dre['particular_pago_centavos'] += $valor;
            } elseif ($tipo === ClinicConta::TIPO_CONVENIO) {
                $dre['convenio_pago_centavos'] += $valor;
            } elseif ($tipo === ClinicConta::TIPO_CORTESIA) {
                $dre['cortesia_centavos'] += $valor;
            }

            $this->accumulateRepasse($byMedico, $conta, $valor, $tipo);
        }

        $repasse = array_values($byMedico);
        usort($repasse, static fn (array $a, array $b): int => $b['total_centavos'] <=> $a['total_centavos']);

        return [
            'dre' => $dre,
            'repasse' => $repasse,
        ];
    }

    /**
     * @param array<string, array{medico_id: ?int, medico_nome: string, qtd: int, total_centavos: int, particular_centavos: int, convenio_centavos: int}> $byMedico
     */
    private function accumulateRepasse(array &$byMedico, ClinicConta $conta, int $valor, string $tipo): void
    {
        if ($tipo === ClinicConta::TIPO_CORTESIA) {
            return;
        }

        $medico = $conta->getAtendimento()?->getMedico()
            ?? $conta->getAgendamento()?->getMedico();

        $key = $medico instanceof User && $medico->getId() !== null
            ? 'm'.$medico->getId()
            : 'sem';
        $nome = $medico instanceof User
            ? (trim((string) $medico->getNome()) !== '' ? (string) $medico->getNome() : 'Médico #'.$medico->getId())
            : 'Sem médico vinculado';

        if (!isset($byMedico[$key])) {
            $byMedico[$key] = [
                'medico_id' => $medico?->getId(),
                'medico_nome' => $nome,
                'qtd' => 0,
                'total_centavos' => 0,
                'particular_centavos' => 0,
                'convenio_centavos' => 0,
            ];
        }

        ++$byMedico[$key]['qtd'];
        $byMedico[$key]['total_centavos'] += $valor;
        if ($tipo === ClinicConta::TIPO_PARTICULAR) {
            $byMedico[$key]['particular_centavos'] += $valor;
        } elseif ($tipo === ClinicConta::TIPO_CONVENIO) {
            $byMedico[$key]['convenio_centavos'] += $valor;
        }
    }
}
