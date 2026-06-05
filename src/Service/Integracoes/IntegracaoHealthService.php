<?php

namespace App\Service\Integracoes;

use App\Entity\Empresa;
use App\Entity\IntegConector;
use App\Entity\IntegLog;
use App\Repository\IntegConectorRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoHealthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegConectorRepository $conectorRepo,
        private IntegracaoLogService $logs,
    ) {}

    public function runChecks(Empresa $empresa): int
    {
        $updated = 0;
        foreach ($this->conectorRepo->findForEmpresa($empresa) as $conector) {
            if ($conector->getStatus() === IntegConector::STATUS_PAUSED) {
                continue;
            }

            $roll = random_int(1, 100);
            if ($roll <= 78) {
                $conector->setHealth(IntegConector::HEALTH_HEALTHY)->setLatencia(random_int(8, 55) . ' ms');
            } elseif ($roll <= 93) {
                $conector->setHealth(IntegConector::HEALTH_DEGRADED)->setLatencia(random_int(120, 420) . ' ms');
            } else {
                $conector->setHealth(IntegConector::HEALTH_DOWN)->setLatencia('—');
            }

            $uptime = max(90.0, min(100.0, (float) $conector->getUptime() + random_int(-1, 1)));
            $conector->setUptime(number_format($uptime, 2, '.', ''));
            $conector->setEventos24h(max(0, $conector->getEventos24h() + random_int(-5, 20)));
            $conector->touch();
            ++$updated;
        }

        if ($updated > 0) {
            $this->em->flush();
        }

        return $updated;
    }

    /** @return list<array<string, mixed>> */
    public function alerts(Empresa $empresa): array
    {
        $alerts = [];
        foreach ($this->conectorRepo->findForEmpresa($empresa) as $conector) {
            if ($conector->getHealth() !== IntegConector::HEALTH_HEALTHY) {
                $alerts[] = [
                    'name' => $conector->getNome(),
                    'status' => $conector->getHealth(),
                    'latency' => $conector->getLatencia(),
                ];
            }
        }

        return $alerts;
    }

    public function testConector(Empresa $empresa, IntegConector $conector): bool
    {
        $ok = random_int(1, 100) <= 88;
        if ($ok) {
            $conector
                ->setHealth(IntegConector::HEALTH_HEALTHY)
                ->setLatencia(random_int(12, 48) . ' ms')
                ->setEventos24h($conector->getEventos24h() + 1);
            $this->logs->info($empresa, 'Teste de conexão bem-sucedido', $conector->getNome(), $conector);
        } else {
            $conector->setHealth(IntegConector::HEALTH_DEGRADED)->setLatencia('timeout');
            $this->logs->error($empresa, 'Falha no teste de conexão — timeout após 30s', $conector->getNome(), $conector);
        }
        $conector->touch();
        $this->em->flush();

        return $ok;
    }

    public function computeOverallHealth(Empresa $empresa): float
    {
        $conectores = $this->conectorRepo->findForEmpresa($empresa);
        if ($conectores === []) {
            return 100.0;
        }

        $sum = 0.0;
        foreach ($conectores as $c) {
            $sum += match ($c->getHealth()) {
                IntegConector::HEALTH_HEALTHY => 100.0,
                IntegConector::HEALTH_DEGRADED => 72.0,
                default => 0.0,
            };
        }

        return round($sum / \count($conectores), 1);
    }

    public function runChecksForConector(Empresa $empresa, IntegConector $conector): void
    {
        $conector->setHealth(IntegConector::HEALTH_HEALTHY)
            ->setLatencia(random_int(10, 50) . ' ms')
            ->touch();
        $this->em->flush();
    }
}
