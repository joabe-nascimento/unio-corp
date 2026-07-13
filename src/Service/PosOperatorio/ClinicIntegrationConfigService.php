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
    ) {}

    /** @return array{webhook_url: string, webhook_events: list<string>, lembretes_sms: bool} */
    public function get(Empresa $empresa): array
    {
        $stored = $this->read($empresa);

        return [
            'webhook_url' => (string) ($stored['webhook_url'] ?? ''),
            'webhook_events' => \is_array($stored['webhook_events'] ?? null)
                ? array_values(array_filter($stored['webhook_events'], 'is_string'))
                : ['alerta_p1', 'questionario_pendente', 'alerta_escalado', 'agenda_confirmacao', 'carteirinha.emitida', 'comprovante.emitido', 'verificacao.sucesso', 'checkin.realizado'],
            'lembretes_sms' => (bool) ($stored['lembretes_sms'] ?? true),
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
}
