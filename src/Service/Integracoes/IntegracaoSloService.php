<?php

namespace App\Service\Integracoes;

use App\Config\IntegracaoFlowRegistry;
use App\Entity\Empresa;
use App\Entity\IntegSlo;
use App\Repository\IntegSloRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoSloService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegSloRepository $repository,
    ) {}

    public function ensureSlos(Empresa $empresa): void
    {
        if ($this->repository->countForEmpresa($empresa) > 0) {
            return;
        }

        $defaults = [
            ['meta_uptime' => '99.50', 'meta_latencia_ms' => 200, 'uptime_atual' => '99.80', 'latencia_atual_ms' => 180],
            ['meta_uptime' => '99.00', 'meta_latencia_ms' => 500, 'uptime_atual' => '97.20', 'latencia_atual_ms' => 620],
            ['meta_uptime' => '99.90', 'meta_latencia_ms' => 100, 'uptime_atual' => '99.95', 'latencia_atual_ms' => 90],
            ['meta_uptime' => '99.00', 'meta_latencia_ms' => 400, 'uptime_atual' => '99.10', 'latencia_atual_ms' => 380],
            ['meta_uptime' => '99.50', 'meta_latencia_ms' => 300, 'uptime_atual' => '98.50', 'latencia_atual_ms' => 310],
        ];

        $flows = IntegracaoFlowRegistry::all();
        foreach ($flows as $i => $flow) {
            $cfg = $defaults[$i % count($defaults)];

            $slo = new IntegSlo();
            $slo->setEmpresa($empresa)
                ->setFlowKey($flow['key'])
                ->setTitulo($flow['titulo'])
                ->setMetaUptime($cfg['meta_uptime'])
                ->setMetaLatenciaMs($cfg['meta_latencia_ms'])
                ->setUptimeAtual($cfg['uptime_atual'])
                ->setLatenciaAtualMs($cfg['latencia_atual_ms'])
                ->setEmBrecha($slo->isEmBrecha());
            $this->em->persist($slo);
        }
        $this->em->flush();
    }

    /** @return list<array<string, mixed>> */
    public function list(Empresa $empresa): array
    {
        return array_map(fn ($s) => $s->toArray(), $this->repository->findForEmpresa($empresa));
    }

    public function update(IntegSlo $slo, float $uptimeAtual, ?int $latenciaMs): void
    {
        $slo->setUptimeAtual((string) $uptimeAtual);
        if ($latenciaMs !== null) {
            $slo->setLatenciaAtualMs($latenciaMs);
        }
        $slo->setEmBrecha($slo->isEmBrecha());
        $this->em->flush();
    }

    /** @return list<array<string, mixed>> */
    public function breaches(Empresa $empresa): array
    {
        return array_filter($this->list($empresa), fn ($s) => $s['em_brecha']);
    }
}
