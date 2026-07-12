<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use App\Entity\PosOperatorioEvento;
use App\Entity\User;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioEventoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PosOperatorioEscalationService
{
    public function __construct(
        private PosOperatorioAlertaRepository $alertas,
        private PosOperatorioEventoRepository $eventos,
        private UserRepository $users,
        private ClinicPolicyConfigService $policy,
        private ClinicDutyRosterService $duty,
        private ClinicChannelDispatcher $channels,
        private PosOperatorioEventRecorder $events,
        private EntityManagerInterface $em,
    ) {}

    /** @return array{escalados: int, ignorados: int} */
    public function processOpenAlerts(Empresa $empresa): array
    {
        $escalados = 0;
        $ignorados = 0;
        $now = new \DateTimeImmutable();
        $levels = $this->policy->get($empresa)['escalacao_horas'];

        foreach ($this->alertas->findAbertosByEmpresa($empresa, 200) as $alerta) {
            if ($alerta->getStatus() !== PosOperatorioAlerta::STATUS_ABERTO
                && $alerta->getStatus() !== PosOperatorioAlerta::STATUS_EM_ATENDIMENTO) {
                ++$ignorados;
                continue;
            }

            $hours = (int) floor(($now->getTimestamp() - $alerta->getCriadoEm()->getTimestamp()) / 3600);
            foreach ($levels as $level) {
                if ($hours < $level || $this->eventos->hasEscalacaoForAlerta($alerta, $level)) {
                    continue;
                }

                $this->escalate($empresa, $alerta, $level);
                ++$escalados;
            }
        }

        if ($escalados > 0) {
            $this->em->flush();
        }

        return ['escalados' => $escalados, 'ignorados' => $ignorados];
    }

    private function escalate(Empresa $empresa, PosOperatorioAlerta $alerta, int $level): void
    {
        $paciente = $alerta->getPaciente();
        $titulo = sprintf('Escalação %dh — alerta %s', $level, $alerta->getPrioridade());
        $mensagem = sprintf(
            '%s (%s): %s — aberto há mais de %d horas.',
            $paciente->getNome(),
            $paciente->getCodigo(),
            $alerta->getMotivo(),
            $level,
        );

        $destinatarios = [];
        if ($level <= 4 || $alerta->getPrioridade() === 'P1') {
            $pick = $this->duty->pickForAlert($empresa, $paciente, $alerta->getPrioridade());
            if ($pick instanceof User) {
                $destinatarios[] = $pick;
            }
            $resp = $alerta->getResponsavel() ?? $paciente->getMedicoResponsavel();
            if ($resp instanceof User) {
                $destinatarios[] = $resp;
            }
        } else {
            $destinatarios = $this->users->findCoordenadoresByEmpresa($empresa);
            if ($destinatarios === []) {
                $pick = $this->duty->pickForAlert($empresa, $paciente, 'P1');
                if ($pick instanceof User) {
                    $destinatarios[] = $pick;
                }
            }
        }

        $seen = [];
        foreach ($destinatarios as $user) {
            if (!$user instanceof User || isset($seen[$user->getId()])) {
                continue;
            }
            $seen[$user->getId()] = true;
            $this->channels->notifyTeam(
                $empresa,
                $user,
                'alerta_escalado',
                $titulo,
                $mensagem,
                $alerta->getPrioridade() === 'P1' ? 'app_pos_operatorio_sala_critica' : 'app_pos_operatorio_alertas',
                'fa-arrow-up-right-dots',
                $level >= 24 ? 'danger' : 'warning',
            );
        }

        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_ESCALACAO,
            sprintf('Escalacao automática %dh do alerta #%d', $level, $alerta->getId()),
            null,
        );

        $this->channels->emitWebhook($empresa, 'alerta_escalado', [
            'alerta_id' => $alerta->getId(),
            'prioridade' => $alerta->getPrioridade(),
            'motivo' => $alerta->getMotivo(),
            'paciente_codigo' => $paciente->getCodigo(),
            'horas' => $level,
            'telefone_paciente' => $paciente->getTelefoneContato(),
            'contato_emergencia' => method_exists($paciente, 'getContatoEmergencia') ? $paciente->getContatoEmergencia() : null,
            'telefone_emergencia' => method_exists($paciente, 'getTelefoneEmergencia') ? $paciente->getTelefoneEmergencia() : null,
        ]);
    }
}
