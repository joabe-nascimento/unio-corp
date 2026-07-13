<?php

namespace App\Service\Organismo\Twin;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicConta;
use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoDayTwinRun;
use App\Entity\PosOperatorioPaciente;
use App\Repository\ClinicAgendamentoRepository;
use App\Repository\ClinicContaRepository;
use App\Repository\Organismo\OrganismoDayTwinRunRepository;
use App\Service\Clinic\ClinicDayPanelService;
use App\Service\Organismo\ClinicNavBadgeService;
use Doctrine\ORM\EntityManagerInterface;

/** Heurísticas ranqueadas de cascata operacional (Gêmeo do Dia). */
final class CascadePredictor
{
    public function __construct(
        private ClinicDayPanelService $dayPanel,
        private ClinicNavBadgeService $badges,
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicContaRepository $contas,
        private OrganismoDayTwinRunRepository $runs,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<array{
     *   id: string,
     *   titulo: string,
     *   probabilidade: int,
     *   impacto: string,
     *   confianca: int,
     *   acao_sugerida: string,
     *   route: string,
     *   detalhe: string
     * }>
     */
    public function predict(Empresa $empresa, bool $persist = true): array
    {
        $day = $this->dayPanel->build($empresa);
        $badges = $this->badges->forEmpresa($empresa);
        $scenarios = [];

        $semQ = $day['sem_questionario'] ?? [];
        foreach (\array_slice($semQ, 0, 5) as $p) {
            $diaPos = (int) ($p['dia_pos'] ?? 0);
            $prob = 35 + min(40, $diaPos * 4);
            if (($p['status'] ?? '') === PosOperatorioPaciente::STATUS_ALERTA) {
                $prob = min(92, $prob + 20);
            }
            $scenarios[] = [
                'id' => 'q-miss-'.($p['id'] ?? 'x'),
                'titulo' => sprintf('%s sem questionário — risco de alerta', $p['nome'] ?? 'Paciente'),
                'probabilidade' => $prob,
                'impacto' => $prob >= 70 ? 'alto' : 'medio',
                'confianca' => 72,
                'acao_sugerida' => 'Disparar lembrete e abrir ficha',
                'route' => 'app_pos_operatorio_questionarios',
                'detalhe' => sprintf('D+%d · status %s', $diaPos, $p['status'] ?? '—'),
            ];
        }

        $hoje = new \DateTimeImmutable('today');
        $amanha = $hoje->modify('+1 day');
        $marcados = $this->agendamentos->createQueryBuilder('a')
            ->andWhere('a.empresa = :empresa')
            ->andWhere('a.inicio >= :from')
            ->andWhere('a.inicio < :to')
            ->andWhere('a.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('from', $hoje)
            ->setParameter('to', $amanha)
            ->setParameter('status', ClinicAgendamento::STATUS_MARCADO)
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        foreach ($marcados as $ag) {
            /** @var ClinicAgendamento $ag */
            $pac = $ag->getPaciente();
            $scenarios[] = [
                'id' => 'noshow-'.$ag->getId(),
                'titulo' => sprintf('Possível no-show: %s', $pac?->getNome() ?? 'horário'),
                'probabilidade' => 58,
                'impacto' => 'medio',
                'confianca' => 65,
                'acao_sugerida' => 'Confirmar presença (WhatsApp / ligação)',
                'route' => 'app_pos_operatorio_agenda',
                'detalhe' => $ag->getInicio()->format('H:i'),
            ];
        }

        $contasAbertas = $this->contas->findByEmpresaAndStatus($empresa, ClinicConta::STATUS_ABERTO, 5);
        foreach ($contasAbertas as $conta) {
            $scenarios[] = [
                'id' => 'conta-'.$conta->getId(),
                'titulo' => sprintf('Conta aberta — %s', $conta->getPaciente()?->getNome() ?? 'particular'),
                'probabilidade' => 62,
                'impacto' => 'financeiro',
                'confianca' => 80,
                'acao_sugerida' => 'Marcar pago ou cortesia',
                'route' => 'app_pos_operatorio_contas',
                'detalhe' => 'Fluxo de caixa do dia em risco',
            ];
        }

        $p1 = (int) ($badges['sala_critica'] ?? 0);
        if ($p1 > 0) {
            $scenarios[] = [
                'id' => 'p1-cascade',
                'titulo' => sprintf('Cascata P1: %d paciente(s) em sala crítica', $p1),
                'probabilidade' => min(95, 70 + ($p1 * 8)),
                'impacto' => 'critico',
                'confianca' => 88,
                'acao_sugerida' => 'Abrir sala crítica e claim imediato',
                'route' => 'app_pos_operatorio_sala_critica',
                'detalhe' => 'Sem resposta rápida, o organismo degrada vitalidade',
            ];
        }

        usort($scenarios, static fn (array $a, array $b): int => ($b['probabilidade'] <=> $a['probabilidade']));
        $scenarios = \array_slice($scenarios, 0, 8);

        if ($persist) {
            $dia = $hoje->setTime(0, 0);
            $run = $this->runs->findForDay($empresa, $dia) ?? (new OrganismoDayTwinRun())->setEmpresa($empresa)->setDia($dia);
            $run->setScenarios($scenarios);
            $this->em->persist($run);
            $this->em->flush();
        }

        return $scenarios;
    }
}
