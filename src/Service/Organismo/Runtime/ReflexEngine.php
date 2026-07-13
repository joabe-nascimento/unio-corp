<?php

namespace App\Service\Organismo\Runtime;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoReflexLog;
use App\Entity\PosOperatorioPaciente;
use App\Repository\Organismo\OrganismoReflexLogRepository;
use App\Service\Organismo\Memory\OrganismMemoryWriter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reflexos determinísticos do organismo — ledger + memória.
 * Ações são registradas; actuators clínicos (alerta/lembrete) ficam no tick orquestrador.
 */
final class ReflexEngine
{
    public function __construct(
        private OrganismoReflexLogRepository $logs,
        private OrganismMemoryWriter $memory,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $vitality
     * @param list<array<string, mixed>> $scenarios
     *
     * @return list<array{code: string, motivo: string, acao: string, alvo: ?string}>
     */
    public function evaluate(Empresa $empresa, array $vitality, array $scenarios): array
    {
        $fired = [];
        $badges = $vitality['badges'] ?? [];
        $raw = $vitality['raw'] ?? [];

        if ((int) ($badges['sala_critica'] ?? 0) > 0 && $this->logs->countTodayByCode($empresa, 'p1_sem_claim') === 0) {
            $fired[] = $this->fire(
                $empresa,
                'p1_sem_claim',
                'Há alerta(s) P1 abertos na sala crítica',
                'escalar_sala_critica',
                'app_pos_operatorio_sala_critica',
                ['p1' => (int) $badges['sala_critica']],
                20,
            );
        }

        if ((int) ($raw['sem_questionario'] ?? 0) >= 2 && $this->logs->countTodayByCode($empresa, 'questionarios_atrasados') === 0) {
            $fired[] = $this->fire(
                $empresa,
                'questionarios_atrasados',
                sprintf('%d pacientes sem questionário hoje', (int) $raw['sem_questionario']),
                'lembrar_questionarios',
                'app_pos_operatorio_questionarios',
                ['count' => (int) $raw['sem_questionario']],
                12,
            );
        }

        if ((int) ($raw['contas_abertas'] ?? 0) >= 2 && $this->logs->countTodayByCode($empresa, 'contas_orfas') === 0) {
            $fired[] = $this->fire(
                $empresa,
                'contas_orfas',
                sprintf('%d contas em aberto', (int) $raw['contas_abertas']),
                'revisar_contas',
                'app_pos_operatorio_contas',
                ['count' => (int) $raw['contas_abertas']],
                10,
            );
        }

        if ((int) ($raw['agenda_sem_confirmacao'] ?? 0) >= 2 && $this->logs->countTodayByCode($empresa, 'agenda_sem_confirmacao') === 0) {
            $fired[] = $this->fire(
                $empresa,
                'agenda_sem_confirmacao',
                'Horários do dia ainda sem confirmação',
                'confirmar_agenda',
                'app_pos_operatorio_agenda',
                ['count' => (int) $raw['agenda_sem_confirmacao']],
                11,
            );
        }

        $score = (int) ($vitality['score'] ?? 100);
        if ($score < 55 && $this->logs->countTodayByCode($empresa, 'vitalidade_critica') === 0) {
            $fired[] = $this->fire(
                $empresa,
                'vitalidade_critica',
                sprintf('Vitalidade do organismo em %d', $score),
                'abrir_pulso',
                'app_pulso',
                ['score' => $score],
                25,
            );
        }

        $top = $scenarios[0] ?? null;
        if (\is_array($top) && (int) ($top['probabilidade'] ?? 0) >= 75 && $this->logs->countTodayByCode($empresa, 'gemeo_risco_alto') === 0) {
            $fired[] = $this->fire(
                $empresa,
                'gemeo_risco_alto',
                (string) ($top['titulo'] ?? 'Risco alto previsto'),
                'atuar_gemeo',
                (string) ($top['route'] ?? 'app_pulso'),
                ['scenario' => $top],
                18,
            );
        }

        if ((int) ($raw['contratos_ativos'] ?? 0) === 0 && (int) ($badges['pacientes_ativos'] ?? 0) > 0
            && $this->logs->countTodayByCode($empresa, 'sem_contratos') === 0) {
            $fired[] = $this->fire(
                $empresa,
                'sem_contratos',
                'Pacientes ativos sem contrato de cuidado executável',
                'gerar_contratos',
                'app_organismo_contratos',
                [],
                14,
            );
        }

        $this->em->flush();

        return $fired;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{code: string, motivo: string, acao: string, alvo: ?string}
     */
    private function fire(
        Empresa $empresa,
        string $code,
        string $motivo,
        string $acao,
        ?string $alvo,
        array $payload,
        int $peso,
        ?PosOperatorioPaciente $paciente = null,
    ): array {
        $log = new OrganismoReflexLog();
        $log->setEmpresa($empresa)
            ->setReflexCode($code)
            ->setMotivo(mb_substr($motivo, 0, 255))
            ->setAcao($acao)
            ->setAlvo($alvo)
            ->setPayload($payload)
            ->setPaciente($paciente);
        $this->em->persist($log);

        $this->memory->remember(
            $empresa,
            'reflexo',
            $motivo,
            ['code' => $code, 'acao' => $acao, 'alvo' => $alvo] + $payload,
            $peso,
            $paciente,
        );

        return [
            'code' => $code,
            'motivo' => $motivo,
            'acao' => $acao,
            'alvo' => $alvo,
        ];
    }
}
