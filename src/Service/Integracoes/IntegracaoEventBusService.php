<?php

namespace App\Service\Integracoes;

use App\Config\IntegracaoFlowRegistry;
use App\Entity\Empresa;
use App\Entity\IntegDomainEvent;
use App\Repository\IntegCausalTraceRepository;
use App\Repository\IntegDomainEventRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoEventBusService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegDomainEventRepository $repository,
        private IntegCausalTraceRepository $traceRepo,
        private IntegracaoLogService $logs,
    ) {}

    public function publish(Empresa $empresa, string $tipo, array $payload, ?string $origem = null): IntegDomainEvent
    {
        $event = new IntegDomainEvent();
        $event->setEmpresa($empresa)->setTipo($tipo)->setPayload($payload)->setOrigem($origem);
        $this->em->persist($event);

        $this->process($empresa, $event);

        $this->em->flush();

        return $event;
    }

    public function process(Empresa $empresa, IntegDomainEvent $event): void
    {
        try {
            $this->updateCausalTrace($empresa, $event);
            $this->logs->info($empresa, 'Evento de domínio processado: ' . $event->getTipo(), $event->getOrigem() ?? 'event_bus');
            $event->setStatus(IntegDomainEvent::STATUS_PROCESSADO)->setProcessadoEm(new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $event->setStatus(IntegDomainEvent::STATUS_FALHOU)->setErroProcessamento($e->getMessage());
        }
    }

    private function updateCausalTrace(Empresa $empresa, IntegDomainEvent $event): void
    {
        $tipo = $event->getTipo();
        $flowKey = null;

        foreach (IntegracaoFlowRegistry::all() as $flow) {
            if (($flow['evento_gatilho'] ?? '') === $tipo) {
                $flowKey = $flow['key'];
                break;
            }
        }

        if ($flowKey === null) {
            return;
        }

        $trace = $this->traceRepo->findOneByFlowKey($empresa, $flowKey);
        if (!$trace) {
            return;
        }

        $currentConf = (float) $trace->getConfiabilidade();
        $newConf = max(50.0, min(100.0, $currentConf + random_int(-3, 8)));
        $trace->setConfiabilidade($newConf);
        $trace->setUltimoEventoEm(new \DateTimeImmutable());
        $trace->setStatus($newConf >= 85 ? 'healthy' : ($newConf >= 60 ? 'degraded' : 'failed'));
    }

    /** @return list<array<string, mixed>> */
    public function recentEvents(Empresa $empresa, int $limit = 20): array
    {
        $events = $this->repository->findForEmpresa($empresa);

        return array_map(fn ($e) => $e->toArray(), array_slice($events, 0, $limit));
    }

    public function seedDemoEvents(Empresa $empresa): void
    {
        if (count($this->repository->findForEmpresa($empresa)) > 3) {
            return;
        }
        $demoEvents = [
            ['rh.admissao.concluida', ['nome' => 'Maria Santos', 'departamento' => 'TI', 'cargo' => 'Analista'], 'hub_rh'],
            ['rh.folha.fechamento', ['periodo' => '2026-05', 'total_funcionarios' => 450, 'valor_total' => 890000], 'hub_rh'],
            ['rh.esocial.evento', ['tipo' => 'S-2200', 'lote' => 'LOTE-2026-001'], 'hub_rh'],
            ['rh.demissao.concluida', ['nome' => 'Carlos Lima', 'motivo' => 'rescisao'], 'hub_rh'],
        ];
        foreach ($demoEvents as [$tipo, $payload, $origem]) {
            $this->publish($empresa, $tipo, $payload, $origem);
        }
    }
}
