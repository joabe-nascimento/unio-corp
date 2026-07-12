<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use App\Entity\PosOperatorioEvento;
use App\Entity\User;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioEventoRepository;
use App\Repository\UserRepository;
use App\Service\PlatformNotificationService;
use Doctrine\ORM\EntityManagerInterface;

final class PosOperatorioEscalationService
{
    /** @var list<int> */
    private const LEVELS = [4, 8, 24];

    public function __construct(
        private PosOperatorioAlertaRepository $alertas,
        private PosOperatorioEventoRepository $eventos,
        private UserRepository $users,
        private PlatformNotificationService $notifications,
        private ClinicWebhookDispatcher $webhooks,
        private PosOperatorioEventRecorder $events,
        private EntityManagerInterface $em,
    ) {}

    /** @return array{escalados: int, ignorados: int} */
    public function processOpenAlerts(Empresa $empresa): array
    {
        $escalados = 0;
        $ignorados = 0;
        $now = new \DateTimeImmutable();

        foreach ($this->alertas->findAbertosByEmpresa($empresa, 200) as $alerta) {
            $hours = (int) floor(($now->getTimestamp() - $alerta->getCriadoEm()->getTimestamp()) / 3600);
            foreach (self::LEVELS as $level) {
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
        $titulo = sprintf('Escalacao %dh — alerta %s', $level, $alerta->getPrioridade());
        $mensagem = sprintf(
            '%s (%s): %s — aberto há mais de %d horas.',
            $paciente->getNome(),
            $paciente->getCodigo(),
            $alerta->getMotivo(),
            $level,
        );

        $destinatarios = match (true) {
            $level <= 4 => array_filter([$alerta->getResponsavel() ?? $paciente->getMedicoResponsavel()]),
            default => $this->users->findCoordenadoresByEmpresa($empresa),
        };

        foreach ($destinatarios as $user) {
            if (!$user instanceof User) {
                continue;
            }
            $this->notifications->notify(
                $empresa,
                $user,
                'pos_operatorio',
                'alerta_escalado',
                $titulo,
                $mensagem,
                'app_pos_operatorio_alertas',
                [],
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

        $this->webhooks->dispatch($empresa, 'alerta_escalado', [
            'alerta_id' => $alerta->getId(),
            'prioridade' => $alerta->getPrioridade(),
            'motivo' => $alerta->getMotivo(),
            'paciente_codigo' => $paciente->getCodigo(),
            'horas' => $level,
            'telefone_paciente' => $paciente->getTelefoneContato(),
            'contato_emergencia' => $paciente->getContatoEmergencia(),
            'telefone_emergencia' => $paciente->getTelefoneEmergencia(),
        ]);
    }
}
