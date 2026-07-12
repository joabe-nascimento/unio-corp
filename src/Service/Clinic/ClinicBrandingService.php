<?php

namespace App\Service\Clinic;

use App\Entity\Empresa;
use App\Service\PlatformConfigService;
use App\Service\PosOperatorio\ClinicConfigStore;

/**
 * White-label por clínica — logo, cores e slogan.
 */
final class ClinicBrandingService
{
    public function __construct(
        private ClinicConfigStore $configStore,
        private PlatformConfigService $platformConfig,
    ) {}

    /** @return array<string, mixed> */
    public function get(Empresa $empresa): array
    {
        $stored = $this->configStore->read($empresa, 'branding');
        $platform = $this->platformConfig->all();

        return array_merge($this->defaults($platform), $stored);
    }

    /** @param array<string, mixed> $data */
    public function save(Empresa $empresa, array $data): void
    {
        $merged = array_merge($this->get($empresa), array_intersect_key($data, array_flip([
            'logo_url', 'logo_mark_url', 'cor_primaria', 'cor_secundaria', 'slogan', 'nome_exibicao', 'favicon_url',
        ])));
        $this->configStore->write($empresa, 'branding', $merged);
    }

    /** @return array<string, mixed> */
    public function forBeneficiary(?Empresa $empresa = null): array
    {
        if ($empresa === null) {
            return $this->defaults($this->platformConfig->all());
        }

        return $this->get($empresa);
    }

    /** @return array<string, mixed> */
    private function defaults(array $platform): array
    {
        if ($platform === []) {
            $platform = $this->platformConfig->all();
        }

        return [
            'logo_url' => (string) ($platform['logo_full_url'] ?? $platform['logo_url'] ?? ''),
            'logo_mark_url' => (string) ($platform['logo_mark_url'] ?? ''),
            'cor_primaria' => (string) ($platform['cor_primaria'] ?? '#4b72be'),
            'cor_secundaria' => '#eaf0fb',
            'slogan' => (string) ($platform['plataforma_tagline'] ?? 'Saúde que acompanha.'),
            'nome_exibicao' => (string) ($platform['plataforma_nome'] ?? 'Unio Saúde'),
            'favicon_url' => (string) ($platform['favicon_url'] ?? ''),
        ];
    }
}
