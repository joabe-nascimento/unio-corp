<?php

namespace App\Service\Integracoes;

use App\Config\IntegracaoCatalogRegistry;
use App\Entity\Empresa;
use App\Entity\IntegApiKey;
use App\Entity\IntegCausalTrace;
use App\Entity\IntegConector;
use App\Entity\IntegLog;
use App\Entity\IntegMapeamento;
use App\Entity\IntegShadowRun;
use App\Entity\IntegSchemaDrift;
use App\Entity\IntegWebhook;
use App\Entity\User;
use App\Repository\IntegConectorRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoSeedService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegConectorRepository $conectorRepo,
    ) {}

    public function seedDemoData(Empresa $empresa, User $user): bool
    {
        if ($this->conectorRepo->countForEmpresa($empresa) > 0) {
            return false;
        }

        $slack = $this->persistConector($empresa, 'slack', IntegConector::HEALTH_HEALTHY, '34 ms', 99.95, 842);
        $this->persistConector($empresa, 'esocial', IntegConector::HEALTH_DEGRADED, '890 ms', 96.20, 156);
        $ad = $this->persistConector($empresa, 'active_directory', IntegConector::HEALTH_HEALTHY, '22 ms', 99.99, 412);
        $this->persistConector($empresa, 'totvs', IntegConector::HEALTH_HEALTHY, '118 ms', 98.80, 67);

        $this->persistWebhook($empresa, $slack, 'Alertas Slack #rh-avisos', IntegWebhook::DIR_OUT, 'rh.admissao.concluida', 'https://hooks.slack.com/services/demo/rh');
        $this->persistWebhook($empresa, $ad, 'Provisionamento AD', IntegWebhook::DIR_OUT, 'rh.usuario.criado', 'https://ldap.empresa.local/provision');

        $this->persistMapeamento($empresa, $ad, 'E-mail corporativo', 'funcionario.email', 'userPrincipalName', 'lowercase');
        $this->persistMapeamento($empresa, $ad, 'Departamento', 'funcionario.departamento', 'department', null);

        $this->persistApiKey($empresa, 'Integração BI — produção', IntegApiKey::AMB_PROD, ['read:rh', 'read:pessoas']);
        $this->persistApiKey($empresa, 'Sandbox desenvolvimento', IntegApiKey::AMB_DEV, ['read:hub']);

        $this->persistLogs($empresa, $slack);

        $this->em->flush();

        $this->seedCausalTraces($empresa);

        return true;
    }

    public function seedCausalTraces(Empresa $empresa): void
    {
        if ($this->em->getRepository(\App\Entity\IntegCausalTrace::class)->count(['empresa' => $empresa]) > 0) {
            return;
        }

        $traces = [
            [
                'flow_key' => 'admissao_provisionamento',
                'titulo' => 'Admissão → AD → Slack',
                'status' => IntegCausalTrace::STATUS_HEALTHY,
                'confiabilidade' => 98.4,
                'ultimo' => '-18 minutes',
                'impacto' => [
                    'tickets_ti' => 0,
                    'usuarios_afetados' => 0,
                    'sla_risco' => false,
                    'hubs_afetados' => [],
                ],
                'tendencia' => [98, 99, 98, 99, 98, 98, 98],
                'previsao' => [
                    'risco_48h' => 8,
                    'mensagem' => 'Latência AD estável — sem incidentes previstos',
                    'acao_sugerida' => 'Nenhuma ação necessária',
                ],
                'nos' => [
                    ['key' => 'ev_rh', 'tipo' => 'evento_negocio', 'hub' => 'rh', 'icon' => 'fa-user-plus', 'label' => 'Admissão concluída', 'detail' => 'Funcionário #1042 — Joana Silva', 'status' => 'ok', 'latency' => '—'],
                    ['key' => 'map_email', 'tipo' => 'mapeamento', 'hub' => 'integracoes', 'icon' => 'fa-right-left', 'label' => 'Mapeamento e-mail', 'detail' => 'funcionario.email → userPrincipalName', 'status' => 'ok', 'latency' => '4 ms'],
                    ['key' => 'conn_ad', 'tipo' => 'conector', 'hub' => 'integracoes', 'icon' => 'fa-plug', 'label' => 'Active Directory', 'detail' => 'Conta provisionada — joana.silva@empresa.com', 'status' => 'ok', 'latency' => '22 ms'],
                    ['key' => 'wh_slack', 'tipo' => 'webhook', 'hub' => 'integracoes', 'icon' => 'fa-bolt', 'label' => 'Webhook Slack', 'detail' => 'rh.admissao.concluida → #rh-avisos', 'status' => 'ok', 'latency' => '34 ms'],
                    ['key' => 'fx_slack', 'tipo' => 'efeito_hub', 'hub' => 'ti', 'icon' => 'fa-bell', 'label' => 'Notificação enviada', 'detail' => 'Time RH alertado no Slack', 'status' => 'ok', 'latency' => '—'],
                ],
            ],
            [
                'flow_key' => 'folha_totvs_sync',
                'titulo' => 'Folha TOTVS — sincronização',
                'status' => IntegCausalTrace::STATUS_HEALTHY,
                'confiabilidade' => 99.1,
                'ultimo' => '-28 minutes',
                'impacto' => [
                    'tickets_ti' => 0,
                    'usuarios_afetados' => 0,
                    'sla_risco' => false,
                    'hubs_afetados' => [],
                ],
                'tendencia' => [99, 99, 98, 99, 99, 99, 99],
                'previsao' => [
                    'risco_48h' => 5,
                    'mensagem' => 'Sincronização batch dentro do SLA',
                    'acao_sugerida' => 'Nenhuma ação necessária',
                ],
                'nos' => [
                    ['key' => 'ev_folha', 'tipo' => 'evento_negocio', 'hub' => 'rh', 'icon' => 'fa-file-invoice-dollar', 'label' => 'Fechamento de folha', 'detail' => 'Competência 05/2026 — 847 registros', 'status' => 'ok', 'latency' => '—'],
                    ['key' => 'conn_totvs', 'tipo' => 'conector', 'hub' => 'integracoes', 'icon' => 'fa-plug', 'label' => 'TOTVS ERP', 'detail' => 'Importação batch concluída', 'status' => 'ok', 'latency' => '118 ms'],
                    ['key' => 'fx_rh', 'tipo' => 'efeito_hub', 'hub' => 'rh', 'icon' => 'fa-check-double', 'label' => 'Folha atualizada', 'detail' => '847 registros sincronizados na UNio', 'status' => 'ok', 'latency' => '—'],
                ],
            ],
            [
                'flow_key' => 'esocial_compliance',
                'titulo' => 'eSocial — compliance S-2200',
                'status' => IntegCausalTrace::STATUS_DEGRADED,
                'confiabilidade' => 72.5,
                'ultimo' => '-45 minutes',
                'impacto' => [
                    'tickets_ti' => 2,
                    'usuarios_afetados' => 3,
                    'sla_risco' => true,
                    'hubs_afetados' => ['rh', 'ti'],
                    'chamados' => ['#4521 — eSocial timeout', '#4523 — Admissão bloqueada'],
                ],
                'tendencia' => [88, 85, 82, 78, 75, 73, 72],
                'previsao' => [
                    'risco_48h' => 82,
                    'mensagem' => 'Alta probabilidade de falha total em 48h se latência eSocial persistir',
                    'acao_sugerida' => 'Ativar circuit breaker + fila assíncrona no conector eSocial',
                ],
                'nos' => [
                    ['key' => 'ev_adm', 'tipo' => 'evento_negocio', 'hub' => 'rh', 'icon' => 'fa-user-check', 'label' => 'Admissão pendente eSocial', 'detail' => '3 admissões aguardando retorno S-2200', 'status' => 'warn', 'latency' => '—'],
                    ['key' => 'conn_esocial', 'tipo' => 'conector', 'hub' => 'integracoes', 'icon' => 'fa-plug', 'label' => 'eSocial', 'detail' => 'Retorno pendente — protocolo não recebido', 'status' => 'warn', 'latency' => '890 ms'],
                    ['key' => 'fx_block', 'tipo' => 'efeito_hub', 'hub' => 'rh', 'icon' => 'fa-lock', 'label' => 'Admissões bloqueadas', 'detail' => '3 processos RH em espera', 'status' => 'warn', 'latency' => '—'],
                    ['key' => 'fx_ti', 'tipo' => 'efeito_hub', 'hub' => 'ti', 'icon' => 'fa-headset', 'label' => 'Chamados TI abertos', 'detail' => '#4521 e #4523 — SLA em risco', 'status' => 'error', 'latency' => '—'],
                ],
            ],
        ];

        foreach ($traces as $row) {
            $trace = (new IntegCausalTrace())
                ->setEmpresa($empresa)
                ->setFlowKey($row['flow_key'])
                ->setTitulo($row['titulo'])
                ->setStatus($row['status'])
                ->setConfiabilidade($row['confiabilidade'])
                ->setImpacto($row['impacto'])
                ->setNos($row['nos']);

            if (isset($row['tendencia'])) {
                $trace->setTendencia($row['tendencia']);
            }
            if (isset($row['previsao'])) {
                $trace->setPrevisao($row['previsao']);
            }

            $ref = new \ReflectionClass($trace);
            $prop = $ref->getProperty('ultimoEventoEm');
            $prop->setAccessible(true);
            $prop->setValue($trace, new \DateTimeImmutable($row['ultimo']));

            $this->em->persist($trace);
        }

        $this->em->flush();
    }

    public function seedCortexExtras(Empresa $empresa): void
    {
        $this->ensureFourthTrace($empresa);
        $this->backfillTraceMetadata($empresa);
        $this->seedSchemaDrifts($empresa);
        $this->seedDefaultShadowRun($empresa);
    }

    private function backfillTraceMetadata(Empresa $empresa): void
    {
        $defaults = [
            'admissao_provisionamento' => [
                'tendencia' => [98, 99, 98, 99, 98, 98, 98],
                'previsao' => ['risco_48h' => 8, 'mensagem' => 'Latência AD estável', 'acao_sugerida' => 'Nenhuma ação necessária'],
            ],
            'folha_totvs_sync' => [
                'tendencia' => [99, 99, 98, 99, 99, 99, 99],
                'previsao' => ['risco_48h' => 5, 'mensagem' => 'Sincronização batch dentro do SLA', 'acao_sugerida' => 'Nenhuma ação necessária'],
            ],
            'esocial_compliance' => [
                'tendencia' => [88, 85, 82, 78, 75, 73, 72],
                'previsao' => ['risco_48h' => 82, 'mensagem' => 'Alta probabilidade de falha em 48h', 'acao_sugerida' => 'Ativar circuit breaker no eSocial'],
            ],
        ];

        $repo = $this->em->getRepository(IntegCausalTrace::class);
        $dirty = false;
        foreach ($repo->findBy(['empresa' => $empresa]) as $trace) {
            $def = $defaults[$trace->getFlowKey()] ?? null;
            if ($def === null) {
                continue;
            }
            if ($trace->getTendencia() === []) {
                $trace->setTendencia($def['tendencia']);
                $dirty = true;
            }
            if ($trace->getPrevisao() === []) {
                $trace->setPrevisao($def['previsao']);
                $dirty = true;
            }
        }
        if ($dirty) {
            $this->em->flush();
        }
    }

    private function ensureFourthTrace(Empresa $empresa): void
    {
        $repo = $this->em->getRepository(IntegCausalTrace::class);
        if ($repo->findOneBy(['empresa' => $empresa, 'flowKey' => 'demissao_offboarding']) !== null) {
            return;
        }

        $trace = (new IntegCausalTrace())
            ->setEmpresa($empresa)
            ->setFlowKey('demissao_offboarding')
            ->setTitulo('Demissão → revogação AD')
            ->setStatus(IntegCausalTrace::STATUS_HEALTHY)
            ->setConfiabilidade(97.2)
            ->setImpacto([
                'tickets_ti' => 0,
                'usuarios_afetados' => 0,
                'sla_risco' => false,
                'hubs_afetados' => [],
            ])
            ->setNos([
                ['key' => 'ev_dem', 'tipo' => 'evento_negocio', 'hub' => 'rh', 'icon' => 'fa-user-minus', 'label' => 'Demissão concluída', 'detail' => 'Desligamento #892 — Carlos Mendes', 'status' => 'ok', 'latency' => '—'],
                ['key' => 'map_off', 'tipo' => 'mapeamento', 'hub' => 'integracoes', 'icon' => 'fa-right-left', 'label' => 'Revogação AD', 'detail' => 'funcionario.email → disableAccount', 'status' => 'ok', 'latency' => '6 ms'],
                ['key' => 'conn_ad2', 'tipo' => 'conector', 'hub' => 'integracoes', 'icon' => 'fa-plug', 'label' => 'Active Directory', 'detail' => 'Conta desabilitada — carlos.mendes@empresa.com', 'status' => 'ok', 'latency' => '19 ms'],
                ['key' => 'conn_esocial2', 'tipo' => 'conector', 'hub' => 'integracoes', 'icon' => 'fa-plug', 'label' => 'eSocial S-2299', 'detail' => 'Evento de desligamento transmitido', 'status' => 'ok', 'latency' => '210 ms'],
                ['key' => 'fx_done', 'tipo' => 'efeito_hub', 'hub' => 'ti', 'icon' => 'fa-shield-check', 'label' => 'Acessos revogados', 'detail' => 'Licenças M365 liberadas', 'status' => 'ok', 'latency' => '—'],
            ])
            ->setTendencia([97, 97, 96, 97, 98, 97, 97])
            ->setPrevisao([
                'risco_48h' => 12,
                'mensagem' => 'Fluxo estável — sem degradação prevista',
                'acao_sugerida' => 'Manter monitoramento padrão',
            ]);

        $ref = new \ReflectionClass($trace);
        $prop = $ref->getProperty('ultimoEventoEm');
        $prop->setAccessible(true);
        $prop->setValue($trace, new \DateTimeImmutable('-3 hours'));

        $this->em->persist($trace);
        $this->em->flush();
    }

    private function seedSchemaDrifts(Empresa $empresa): void
    {
        if ($this->em->getRepository(IntegSchemaDrift::class)->count(['empresa' => $empresa]) > 0) {
            return;
        }

        $ad = $this->conectorRepo->findByCatalogoId($empresa, 'active_directory');
        $esocial = $this->conectorRepo->findByCatalogoId($empresa, 'esocial');

        $drifts = [
            [
                'conector' => $ad,
                'flow_key' => 'admissao_provisionamento',
                'origem' => 'funcionario.departamento',
                'esperado' => 'department',
                'detectado' => 'deptCode',
                'severidade' => IntegSchemaDrift::SEV_MEDIA,
                'sugestao' => 'Atualizar mapeamento para deptCode ou solicitar rollback no AD. Execute Shadow Replay antes de publicar.',
            ],
            [
                'conector' => $esocial,
                'flow_key' => 'esocial_compliance',
                'origem' => 'funcionario.cpf',
                'esperado' => 'cpfTrab',
                'detectado' => 'cpfTrab (formato alterado)',
                'severidade' => IntegSchemaDrift::SEV_CRITICA,
                'sugestao' => 'eSocial passou a exigir CPF sem pontuação. Aplicar transformação remove_pontuacao no mapeamento.',
            ],
            [
                'conector' => $ad,
                'flow_key' => 'demissao_offboarding',
                'origem' => 'funcionario.matricula',
                'esperado' => 'employeeID',
                'detectado' => 'employeeID',
                'severidade' => IntegSchemaDrift::SEV_BAIXA,
                'sugestao' => 'Drift cosmético — nomenclatura interna alterada, sem impacto funcional.',
                'resolvido' => true,
            ],
        ];

        foreach ($drifts as $row) {
            $d = (new IntegSchemaDrift())
                ->setEmpresa($empresa)
                ->setConector($row['conector'])
                ->setFlowKey($row['flow_key'])
                ->setCampoOrigem($row['origem'])
                ->setCampoEsperado($row['esperado'])
                ->setCampoDetectado($row['detectado'])
                ->setSeveridade($row['severidade'])
                ->setSugestao($row['sugestao'])
                ->setResolvido($row['resolvido'] ?? false);
            $this->em->persist($d);
        }

        $this->em->flush();
    }

    private function seedDefaultShadowRun(Empresa $empresa): void
    {
        if ($this->em->getRepository(IntegShadowRun::class)->count(['empresa' => $empresa]) > 0) {
            return;
        }

        $maps = $this->em->getRepository(IntegMapeamento::class)->findBy(['empresa' => $empresa], ['id' => 'ASC'], 1);
        $map = $maps[0] ?? null;

        $run = (new IntegShadowRun())
            ->setEmpresa($empresa)
            ->setMapeamento($map)
            ->setMapeamentoNome($map?->getNome() ?? 'E-mail corporativo')
            ->setCampoOrigem($map?->getCampoOrigem() ?? 'funcionario.email')
            ->setCampoDestinoAtual($map?->getCampoDestino() ?? 'userPrincipalName')
            ->setCampoDestinoProposto('mail')
            ->setPeriodoDias(7)
            ->setTotalEventos(1284)
            ->setSucesso(1271)
            ->setFalhas(10)
            ->setDuplicatas(3)
            ->setAmostras([
                ['payload' => 'joana.silva@empresa.com', 'atual' => 'joana.silva@empresa.com', 'proposto' => 'joana.silva@empresa.com', 'resultado' => 'ok', 'timestamp' => '29/05 14:22'],
                ['payload' => 'ana_p@empresa.com', 'atual' => 'ana_p@empresa.com', 'proposto' => '', 'resultado' => 'fail', 'motivo' => 'Campo mail rejeitado', 'timestamp' => '29/05 11:05'],
            ]);

        $this->em->persist($run);
        $this->em->flush();
    }

    private function persistConector(
        Empresa $empresa,
        string $catalogoId,
        string $health,
        string $latencia,
        float $uptime,
        int $eventos,
    ): IntegConector {
        $catalog = IntegracaoCatalogRegistry::find($catalogoId);
        if ($catalog === null) {
            throw new \RuntimeException('Catálogo inválido: ' . $catalogoId);
        }

        $conector = (new IntegConector())
            ->setEmpresa($empresa)
            ->setCatalogoId($catalogoId)
            ->setNome($catalog['nome'])
            ->setCategoria($catalog['categoria'])
            ->setHealth($health)
            ->setLatencia($latencia)
            ->setUptime(number_format($uptime, 2, '.', ''))
            ->setEventos24h($eventos)
            ->setHubsAlvo($catalog['hubs']);

        $this->em->persist($conector);

        return $conector;
    }

    private function persistWebhook(
        Empresa $empresa,
        IntegConector $conector,
        string $nome,
        string $direcao,
        string $evento,
        string $url,
    ): void {
        $wh = (new IntegWebhook())
            ->setEmpresa($empresa)
            ->setConector($conector)
            ->setNome($nome)
            ->setDirecao($direcao)
            ->setEvento($evento)
            ->setUrl($url)
            ->setUltimoDisparo(new \DateTimeImmutable('-' . random_int(5, 180) . ' minutes'));

        $this->em->persist($wh);
    }

    private function persistMapeamento(
        Empresa $empresa,
        IntegConector $conector,
        string $nome,
        string $origem,
        string $destino,
        ?string $transform,
    ): void {
        $map = (new IntegMapeamento())
            ->setEmpresa($empresa)
            ->setConector($conector)
            ->setNome($nome)
            ->setCampoOrigem($origem)
            ->setCampoDestino($destino)
            ->setTransformacao($transform);

        $this->em->persist($map);
    }

    /** @param list<string> $scopes */
    private function persistApiKey(Empresa $empresa, string $nome, string $ambiente, array $scopes): void
    {
        $key = (new IntegApiKey())
            ->setEmpresa($empresa)
            ->setNome($nome)
            ->setPrefix('unio_' . ($ambiente === IntegApiKey::AMB_DEV ? 'dev' : 'live'))
            ->setHash(hash('sha256', bin2hex(random_bytes(16))))
            ->setScopes($scopes)
            ->setAmbiente($ambiente)
            ->setUltimoUso(new \DateTimeImmutable('-' . random_int(1, 72) . ' hours'));

        $this->em->persist($key);
    }

    private function persistLogs(Empresa $empresa, IntegConector $slack): void
    {
        $entries = [
            [IntegLog::LEVEL_INFO, 'Slack', 'Notificação enviada — admissão #1042', '-12 minutes'],
            [IntegLog::LEVEL_INFO, 'TOTVS ERP', 'Sync folha — 847 registros importados', '-28 minutes'],
            [IntegLog::LEVEL_WARN, 'eSocial', 'Retorno pendente — evento S-2200 aguardando protocolo', '-45 minutes'],
            [IntegLog::LEVEL_ERROR, 'Webhook', 'Timeout ao disparar POST — retry 2/5', '-1 hour'],
            [IntegLog::LEVEL_INFO, 'Active Directory', 'Conta provisionada — joana.silva@empresa.com', '-2 hours'],
        ];

        foreach ($entries as [$nivel, $origem, $msg, $offset]) {
            $log = (new IntegLog())
                ->setEmpresa($empresa)
                ->setNivel($nivel)
                ->setOrigem($origem)
                ->setMensagem($msg)
                ->setConector(str_contains(strtolower($origem), 'slack') ? $slack : null);

            $ref = new \ReflectionClass($log);
            $prop = $ref->getProperty('criadoEm');
            $prop->setAccessible(true);
            $prop->setValue($log, new \DateTimeImmutable($offset));

            $this->em->persist($log);
        }
    }
}
