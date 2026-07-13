<?php

namespace App\Service\Organismo\Vitality;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoVitalitySnapshot;
use App\Repository\Organismo\OrganismoVitalitySnapshotRepository;
use App\Service\Organismo\Runtime\OrganRegistry;
use App\Service\Organismo\Runtime\VitalReader;
use Doctrine\ORM\EntityManagerInterface;

final class VitalityScoreService
{
    public function __construct(
        private OrganRegistry $organs,
        private VitalReader $vitals,
        private OrganismoVitalitySnapshotRepository $snapshots,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{
     *   score: int,
     *   nivel: string,
     *   tendencia: int|null,
     *   orgaos: list<array{id: string, label: string, icon: string, score: int, weight: float, signals: list<string>}>,
     *   rede: array{opt_in: bool, ranking_local_only: bool},
     *   raw: array<string, mixed>
     * }
     */
    public function compute(Empresa $empresa, bool $persist = false): array
    {
        $reading = $this->vitals->read($empresa);
        $orgaoCards = [];
        $weighted = 0.0;
        $weightSum = 0.0;

        foreach ($this->organs->all() as $meta) {
            $id = $meta['id'];
            $organ = $reading['organs'][$id] ?? ['score' => 100, 'signals' => []];
            $score = (int) ($organ['score'] ?? 100);
            $weight = (float) $meta['weight'];
            $weighted += $score * $weight;
            $weightSum += $weight;
            $orgaoCards[] = [
                'id' => $id,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'score' => $score,
                'weight' => $weight,
                'signals' => $organ['signals'] ?? [],
            ];
        }

        $score = (int) round($weightSum > 0 ? $weighted / $weightSum : 100);
        $nivel = $this->nivelFromScore($score);
        $tendencia = null;
        $latest = $this->snapshots->findLatest($empresa);
        if ($latest !== null) {
            $tendencia = $score - $latest->getScore();
        }

        $result = [
            'score' => $score,
            'nivel' => $nivel,
            'tendencia' => $tendencia,
            'orgaos' => $orgaoCards,
            'rede' => [
                'opt_in' => false,
                'ranking_local_only' => true,
            ],
            'raw' => $reading['raw'] ?? [],
            'badges' => $reading['badges'] ?? [],
            'day' => $reading['day'] ?? [],
        ];

        if ($persist) {
            $snap = new OrganismoVitalitySnapshot();
            $snap->setEmpresa($empresa)
                ->setScore($score)
                ->setNivel($nivel)
                ->setTendencia($tendencia)
                ->setBreakdown([
                    'orgaos' => $orgaoCards,
                    'raw' => $result['raw'],
                ]);
            $this->em->persist($snap);
            $this->em->flush();
        }

        return $result;
    }

    public function nivelFromScore(int $score): string
    {
        if ($score < 55) {
            return 'critico';
        }
        if ($score < 78) {
            return 'atento';
        }

        return 'saudavel';
    }

    /** Map legacy Pulso labels (intenso → critico for deep stress). */
    public function toPulsoNivel(string $nivel): string
    {
        return match ($nivel) {
            'critico' => 'intenso',
            'atento' => 'atento',
            default => 'saudavel',
        };
    }
}
