<?php

namespace App\Service\Organismo\Runtime;

use App\Entity\Empresa;
use App\Repository\Organismo\OrganismoDayTwinRunRepository;
use App\Repository\Organismo\OrganismoReflexLogRepository;
use App\Repository\Organismo\OrganismoVitalitySnapshotRepository;
use App\Service\Organismo\Memory\OrganismMemoryQuery;
use App\Service\Organismo\Twin\DayTwinBuilder;
use App\Service\Organismo\Vitality\VitalityScoreService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Organismo\OrganismoFeature;

/** Orquestra tick do Organismo Runtime (vitais → gêmeo → reflexos). */
final class OrganRuntime
{
    public function __construct(
        private OrganismoFeature $feature,
        private OrganismoCopyService $copy,
        private VitalityScoreService $vitality,
        private DayTwinBuilder $twin,
        private ReflexEngine $reflexes,
        private OrganismMemoryQuery $memory,
        private OrganismoReflexLogRepository $reflexLogs,
        private OrganismoVitalitySnapshotRepository $vitalitySnapshots,
        private OrganismoDayTwinRunRepository $twinRuns,
    ) {
    }

    public function isClinicRuntime(): bool
    {
        return $this->feature->isEnabled() && $this->copy->isClinicProfile();
    }

    /**
     * @return array{
     *   vitality: array<string, mixed>,
     *   twin: array<string, mixed>,
     *   reflexes: list<array<string, mixed>>,
     *   memory: array<string, mixed>,
     *   reflex_feed: list<array<string, mixed>>
     * }
     */
    public function tick(Empresa $empresa, bool $persist = true): array
    {
        $vitality = $this->vitality->compute($empresa, $persist);
        $twin = $this->twin->build($empresa, $persist);
        $fired = $persist
            ? $this->reflexes->evaluate($empresa, $vitality, $twin['scenarios'])
            : [];
        $memory = $this->memory->forEmpresa($empresa);
        $feed = [];
        foreach ($this->reflexLogs->findRecent($empresa, 6) as $log) {
            $feed[] = [
                'code' => $log->getReflexCode(),
                'motivo' => $log->getMotivo(),
                'acao' => $log->getAcao(),
                'alvo' => $log->getAlvo(),
                'em' => $log->getCriadoEm()->format('d/m H:i'),
            ];
        }

        return [
            'vitality' => $vitality,
            'twin' => $twin,
            'reflexes' => $fired,
            'memory' => $memory,
            'reflex_feed' => $feed,
        ];
    }

    /**
     * Snapshot para Pulso — persiste no máximo 1 snapshot/dia e 1 gêmeo/dia.
     *
     * @return array<string, mixed>
     */
    public function pulsoPayload(Empresa $empresa): array
    {
        if (!$this->isClinicRuntime()) {
            return [];
        }

        $today = new \DateTimeImmutable('today');
        $latest = $this->vitalitySnapshots->findLatest($empresa);
        $needVitalityPersist = $latest === null || $latest->getCriadoEm() < $today;
        $needTwinPersist = $this->twinRuns->findForDay($empresa, $today) === null;

        $vitality = $this->vitality->compute($empresa, $needVitalityPersist);
        $twin = $this->twin->build($empresa, $needTwinPersist);
        if ($needVitalityPersist || $needTwinPersist) {
            $this->reflexes->evaluate($empresa, $vitality, $twin['scenarios']);
        }

        $memory = $this->memory->forEmpresa($empresa);
        $feed = [];
        foreach ($this->reflexLogs->findRecent($empresa, 6) as $log) {
            $feed[] = [
                'code' => $log->getReflexCode(),
                'motivo' => $log->getMotivo(),
                'acao' => $log->getAcao(),
                'alvo' => $log->getAlvo(),
                'em' => $log->getCriadoEm()->format('d/m H:i'),
            ];
        }

        return [
            'vitalidade' => [
                'score' => $vitality['score'],
                'nivel' => $vitality['nivel'],
                'pulso_nivel' => $this->vitality->toPulsoNivel((string) $vitality['nivel']),
                'tendencia' => $vitality['tendencia'],
                'orgaos' => $vitality['orgaos'],
            ],
            'rede' => $vitality['rede'],
            'gemeo' => $twin['top'],
            'memoria' => $memory,
            'reflexos' => $feed,
        ];
    }
}
