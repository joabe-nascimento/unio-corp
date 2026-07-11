<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;

/**
 * Configurações leves de integração por clínica (arquivo local, sem migration).
 */
final class ClinicIntegrationConfigService
{
    public function __construct(
        private string $projectDir,
    ) {}

    /** @return array{webhook_url: string, webhook_events: list<string>, lembretes_sms: bool} */
    public function get(Empresa $empresa): array
    {
        $stored = $this->read($empresa);

        return [
            'webhook_url' => (string) ($stored['webhook_url'] ?? ''),
            'webhook_events' => \is_array($stored['webhook_events'] ?? null)
                ? array_values(array_filter($stored['webhook_events'], 'is_string'))
                : ['alerta_p1', 'questionario_pendente'],
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

    /** @return array<string, mixed> */
    private function read(Empresa $empresa): array
    {
        $path = $this->path($empresa);
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    private function write(Empresa $empresa, array $data): void
    {
        $dir = dirname($this->path($empresa));
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar diretório de configuração clínica.');
        }
        file_put_contents(
            $this->path($empresa),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    private function path(Empresa $empresa): string
    {
        return sprintf('%s/var/clinic/integracoes-%d.json', rtrim($this->projectDir, '/\\'), $empresa->getId());
    }
}
