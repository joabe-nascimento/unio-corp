<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\IntegConector;
use App\Entity\TiChamado;
use App\Entity\TiIntegracao;
use App\Repository\IntegConectorRepository;
use App\Repository\TiChamadoRepository;
use App\Repository\TiIntegracaoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TiIntegrationHealthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiIntegracaoRepository $repository,
        private IntegConectorRepository $integConectorRepository,
        private TiChamadoRepository $chamadoRepository,
        private UserRepository $userRepository,
        private TiNotificationService $notifications,
    ) {}

    /** Executa health check — sincroniza com IntegConector se disponível, senão simula. */
    public function runChecks(Empresa $empresa): int
    {
        $conectores = $this->integConectorRepository->findForEmpresa($empresa);

        if (!empty($conectores)) {
            return $this->syncFromIntegConectores($empresa, $conectores);
        }

        // Fallback: simulação local
        return $this->runSimulatedChecks($empresa);
    }

    /**
     * Sincroniza saúde dos TiIntegracao a partir dos IntegConector reais.
     * @param list<IntegConector> $conectores
     */
    public function syncFromIntegConectores(Empresa $empresa, array $conectores): int
    {
        $updated = 0;
        foreach ($conectores as $conector) {
            if ($conector->getStatus() === IntegConector::STATUS_PAUSED) {
                continue;
            }

            $prevHealth = $conector->getHealth();

            // Busca ou cria TiIntegracao correspondente
            $integration = $this->repository->findOneBy([
                'empresa' => $empresa,
                'nome' => $conector->getNome(),
            ]);

            if ($integration === null) {
                $integration = new TiIntegracao();
                $integration->setEmpresa($empresa)
                    ->setNome($conector->getNome())
                    ->setStatus($conector->getHealth())
                    ->setLatencia($conector->getLatencia())
                    ->setUptime((string)(int)(float)$conector->getUptime());
                $this->em->persist($integration);
            } else {
                $integration->setStatus($conector->getHealth())
                    ->setLatencia($conector->getLatencia())
                    ->setUptime((string)(int)(float)$conector->getUptime());
            }

            // Cria incidente automático se saúde piorou
            if ($prevHealth === IntegConector::HEALTH_HEALTHY
                && $conector->getHealth() !== IntegConector::HEALTH_HEALTHY) {
                $this->createIncidentIfNeeded($empresa, $conector, $prevHealth);
            }

            ++$updated;
        }

        if ($updated > 0) {
            $this->em->flush();
        }

        return $updated;
    }

    /** Retorna array de NOC systems diretamente dos IntegConector (sem TiIntegracao). */
    public function buildNocFromIntegConectores(Empresa $empresa): array
    {
        $conectores = $this->integConectorRepository->findForEmpresa($empresa);
        if (empty($conectores)) {
            return [];
        }

        $iconMap = [
            'identidade'    => 'fa-shield-halved',
            'rh'            => 'fa-users',
            'financeiro'    => 'fa-building-columns',
            'produtividade' => 'fa-comment-dots',
            'dados'         => 'fa-database',
        ];

        $nocSystems = [];
        foreach ($conectores as $conector) {
            $status = match ($conector->getHealth()) {
                IntegConector::HEALTH_DOWN      => 'incident',
                IntegConector::HEALTH_DEGRADED  => 'degraded',
                default                          => 'operational',
            };

            $openIncidents = $this->countOpenIncidentsForConector($empresa, $conector->getCatalogoId());

            $nocSystems[] = [
                'name'               => $conector->getNome(),
                'status'             => $status,
                'uptime'             => number_format((float)$conector->getUptime(), 1) . '%',
                'latency'            => $conector->getLatencia(),
                'icon'               => $iconMap[$conector->getCategoria()] ?? 'fa-plug',
                'events'             => $conector->getEventos24h(),
                'observatorio_url'   => '/integracoes/observatorio',
                'integ_conector_id'  => $conector->getId(),
                'open_incidents'     => $openIncidents,
            ];
        }

        return $nocSystems;
    }

    /**
     * Cria chamado TI automático quando um conector degrada/cai.
     * Evita duplicatas verificando chamados com tag 'automatico' + catalogoId abertos.
     */
    public function createIncidentIfNeeded(Empresa $empresa, IntegConector $conector, string $previousHealth): ?TiChamado
    {
        $catalogoId = $conector->getCatalogoId();

        // Verifica duplicata: chamado aberto com essa tag
        foreach ($this->chamadoRepository->findByEmpresa($empresa) as $existing) {
            if ($existing->getStatus() === TiChamado::STATUS_RESOLVIDO) {
                continue;
            }
            if (\in_array($catalogoId, $existing->getTags(), true)
                && \in_array('automatico', $existing->getTags(), true)) {
                return null; // já existe incidente aberto
            }
        }

        // Obtém solicitante (primeiro admin ativo, fallback any)
        $solicitante = $this->getSystemUser($empresa);
        if ($solicitante === null) {
            return null;
        }

        $health = $conector->getHealth();
        $prioridade = $health === IntegConector::HEALTH_DOWN ? 'P1' : 'P2';
        $impacto = $health === IntegConector::HEALTH_DOWN ? 'alto' : 'medio';
        $healthLabel = $health === IntegConector::HEALTH_DOWN ? 'indisponível' : 'degradado';

        $hubsStr = implode(', ', $conector->getHubsAlvo()) ?: 'múltiplos módulos';

        $num = $this->chamadoRepository->nextCodigoNumber($empresa);
        $codigo = 'TK-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);

        $chamado = new TiChamado();
        $chamado->setEmpresa($empresa)
            ->setSolicitante($solicitante)
            ->setCodigo($codigo)
            ->setTitulo('Falha de integração: ' . $conector->getNome())
            ->setResumo(
                'O conector **' . $conector->getNome() . '** está ' . $healthLabel . ' desde ' .
                (new \DateTimeImmutable())->format('d/m/Y H:i') . '.' . "\n\n" .
                'Latência: ' . $conector->getLatencia() . "\n" .
                'Uptime: ' . number_format((float)$conector->getUptime(), 1) . '%' . "\n" .
                'Núcleos afetados: ' . $hubsStr . "\n\n" .
                'Este chamado foi criado automaticamente pelo monitor de integrações.'
            )
            ->setCategoria('integracao')
            ->setPrioridade($prioridade)
            ->setImpacto($impacto)
            ->setTags(['integracao', 'automatico', $catalogoId])
            ->setIntegConectorId($catalogoId);

        $chamado->addTimelineEvent(
            'Chamado criado automaticamente — ' . $conector->getNome() . ' ' . $healthLabel,
            'Monitor NOC'
        );

        $this->em->persist($chamado);
        $this->em->flush();

        // Notifica gestores TI
        $users = $this->userRepository->findActiveByEmpresa($empresa);
        foreach ($users as $user) {
            $this->notifications->notify(
                $empresa,
                $user,
                'incidente_auto',
                'Incidente automático: ' . $conector->getNome(),
                $conector->getNome() . ' está ' . $healthLabel . '. Chamado ' . $codigo . ' aberto automaticamente.',
                '/ti/chamados/' . $codigo,
            );
        }

        return $chamado;
    }

    private function countOpenIncidentsForConector(Empresa $empresa, string $catalogoId): int
    {
        $count = 0;
        foreach ($this->chamadoRepository->findByEmpresa($empresa) as $chamado) {
            if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
                continue;
            }
            if (\in_array($catalogoId, $chamado->getTags(), true)) {
                ++$count;
            }
        }
        return $count;
    }

    private function getSystemUser(Empresa $empresa): ?\App\Entity\User
    {
        $users = $this->userRepository->findActiveByEmpresa($empresa);
        foreach ($users as $user) {
            if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }
        return $users[0] ?? null;
    }

    private function runSimulatedChecks(Empresa $empresa): int
    {
        $updated = 0;
        foreach ($this->repository->findBy(['empresa' => $empresa]) as $integration) {
            $roll = random_int(1, 100);
            if ($roll <= 75) {
                $integration->setStatus('healthy')->setLatencia(random_int(8, 45) . ' ms');
            } elseif ($roll <= 92) {
                $integration->setStatus('degraded')->setLatencia(random_int(120, 280) . ' ms');
            } else {
                $integration->setStatus('down')->setLatencia('—');
            }
            $integration->setUptime((string) max(90, min(100, (int) $integration->getUptime() + random_int(-2, 2))));
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

        // Prefer real IntegConector data
        $conectores = $this->integConectorRepository->findForEmpresa($empresa);
        if (!empty($conectores)) {
            foreach ($conectores as $conector) {
                if ($conector->getHealth() !== IntegConector::HEALTH_HEALTHY) {
                    $alerts[] = [
                        'name'    => $conector->getNome(),
                        'status'  => $conector->getHealth(),
                        'latency' => $conector->getLatencia(),
                    ];
                }
            }
            return $alerts;
        }

        // Fallback to TiIntegracao
        foreach ($this->repository->findBy(['empresa' => $empresa]) as $int) {
            if ($int->getStatus() !== 'healthy') {
                $alerts[] = [
                    'name'    => $int->getNome(),
                    'status'  => $int->getStatus(),
                    'latency' => $int->getLatencia(),
                ];
            }
        }

        return $alerts;
    }
}
