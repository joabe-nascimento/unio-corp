<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;

/**
 * Configurações leves de integração por clínica (arquivo local, sem migration).
 */
final class ClinicIntegrationConfigService
{
    public function __construct(
        private ClinicConfigStore $store,
        private string $asaasApiKey = '',
        private string $asaasApiKeysJson = '',
    ) {}

    /**
     * @return array{
     *     webhook_url: string,
     *     webhook_events: list<string>,
     *     lembretes_sms: bool,
     *     asaas_env: string,
     *     asaas_enabled: bool
     * }
     */
    public function get(Empresa $empresa): array
    {
        $stored = $this->read($empresa);
        $asaas = $this->asaasConfig($empresa);

        return [
            'webhook_url' => (string) ($stored['webhook_url'] ?? ''),
            'webhook_events' => \is_array($stored['webhook_events'] ?? null)
                ? array_values(array_filter($stored['webhook_events'], 'is_string'))
                : ['alerta_p1', 'questionario_pendente', 'alerta_escalado', 'agenda_confirmacao', 'carteirinha.emitida', 'comprovante.emitido', 'verificacao.sucesso', 'checkin.realizado'],
            'lembretes_sms' => (bool) ($stored['lembretes_sms'] ?? true),
            'asaas_env' => $asaas['asaas_env'],
            'asaas_enabled' => $asaas['asaas_enabled'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(Empresa $empresa, array $data): void
    {
        $current = $this->read($empresa);
        if (isset($data['webhook_url']) && \is_string($data['webhook_url'])) {
            $current['webhook_url'] = trim($data['webhook_url']);
        }
        if (isset($data['lembretes_sms'])) {
            $current['lembretes_sms'] = (bool) $data['lembretes_sms'];
        }
        $this->write($empresa, $current);
    }

    /**
     * @param array{asaas_env?: string, asaas_enabled?: bool} $data
     */
    public function saveAsaas(Empresa $empresa, array $data): void
    {
        $current = $this->read($empresa);
        // Remove chave legada em texto puro; credenciais ficam somente no ambiente do servidor.
        unset($current['asaas_api_key']);
        if (isset($data['asaas_env']) && \is_string($data['asaas_env'])) {
            $env = strtolower(trim($data['asaas_env']));
            $current['asaas_env'] = \in_array($env, ['sandbox', 'production'], true) ? $env : 'sandbox';
        }
        if (isset($data['asaas_enabled'])) {
            $current['asaas_enabled'] = (bool) $data['asaas_enabled'];
        }
        $this->write($empresa, $current);
    }

    /**
     * @return array{asaas_api_key: string, asaas_env: string, asaas_enabled: bool}
     */
    public function asaasConfig(Empresa $empresa): array
    {
        $stored = $this->read($empresa);
        $env = strtolower((string) ($stored['asaas_env'] ?? 'sandbox'));

        return [
            'asaas_api_key' => $this->resolveAsaasApiKey($empresa),
            'asaas_env' => \in_array($env, ['sandbox', 'production'], true) ? $env : 'sandbox',
            'asaas_enabled' => (bool) ($stored['asaas_enabled'] ?? false),
        ];
    }

    public function asaasConfigured(Empresa $empresa): bool
    {
        $cfg = $this->asaasConfig($empresa);

        return $cfg['asaas_enabled'] && $cfg['asaas_api_key'] !== '';
    }

    public function webhookConfigured(Empresa $empresa): bool
    {
        $url = $this->get($empresa)['webhook_url'];

        return $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /** @return list<int> */
    public function getPlantaoUserIds(Empresa $empresa): array
    {
        $stored = $this->read($empresa);
        $ids = $stored['plantao_user_ids'] ?? [];

        if (!\is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    }

    /** @param list<int> $ids */
    public function setPlantaoUserIds(Empresa $empresa, array $ids): void
    {
        $current = $this->read($empresa);
        $current['plantao_user_ids'] = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        $this->write($empresa, $current);
    }

    /** @return array<string, mixed> */
    private function read(Empresa $empresa): array
    {
        return $this->store->read($empresa, 'integracoes');
    }

    /** @param array<string, mixed> $data */
    private function write(Empresa $empresa, array $data): void
    {
        $this->store->write($empresa, 'integracoes', $data);
    }

    private function resolveAsaasApiKey(Empresa $empresa): string
    {
        $map = json_decode($this->asaasApiKeysJson, true);
        if (\is_array($map)) {
            $cnpj = preg_replace('/\D+/', '', (string) $empresa->getCnpj()) ?? '';
            $key = trim((string) ($map[$cnpj] ?? ''));
            if ($key !== '') {
                return $key;
            }
        }

        return trim($this->asaasApiKey);
    }
}
