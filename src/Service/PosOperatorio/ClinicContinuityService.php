<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Repository\EmpresaRepository;

/**
 * Orquestra lembretes + escalação (continuidade clínica).
 */
final class ClinicContinuityService
{
    public function __construct(
        private EmpresaRepository $empresas,
        private PosOperatorioReminderService $reminders,
        private PosOperatorioEscalationService $escalation,
    ) {}

    /**
     * @return array{empresas: int, lembretes: int, escalacoes: int}
     */
    public function runAll(?int $empresaId = null): array
    {
        $list = $empresaId
            ? array_filter([$this->empresas->find($empresaId)])
            : $this->empresas->findBy(['ativo' => true]);

        $lembretes = 0;
        $escalacoes = 0;
        $count = 0;

        foreach ($list as $empresa) {
            if (!$empresa instanceof Empresa) {
                continue;
            }
            ++$count;
            $lembretes += $this->reminders->sendPendingQuestionnaireReminders($empresa)['enviados'];
            $escalacoes += $this->escalation->processOpenAlerts($empresa)['escalados'];
        }

        return ['empresas' => $count, 'lembretes' => $lembretes, 'escalacoes' => $escalacoes];
    }
}
