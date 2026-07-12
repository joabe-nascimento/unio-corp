<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicEmpresaConfig;
use App\Entity\Empresa;
use App\Repository\ClinicEmpresaConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persistência clínica por empresa (banco), com migração automática de JSON em var/clinic/.
 */
final class ClinicConfigStore
{
    public function __construct(
        private ClinicEmpresaConfigRepository $repository,
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {}

    /** @return array<string, mixed> */
    public function read(Empresa $empresa, string $section): array
    {
        $this->migrateFromFileIfNeeded($empresa, $section);

        $config = $this->repository->findForEmpresa($empresa);
        if (!$config instanceof ClinicEmpresaConfig) {
            return [];
        }

        return match ($section) {
            'produtos' => $config->getProdutos(),
            'guias' => $config->getGuias(),
            'integracoes' => $config->getIntegracoes(),
            'branding' => $config->getBranding(),
            'planos_limites' => $config->getPlanosLimites(),
            'onboarding' => $config->getOnboarding(),
            default => [],
        };
    }

    /** @param array<string, mixed> $data */
    public function write(Empresa $empresa, string $section, array $data): void
    {
        $config = $this->repository->findForEmpresa($empresa);
        if (!$config instanceof ClinicEmpresaConfig) {
            $config = (new ClinicEmpresaConfig())->setEmpresa($empresa);
            $this->em->persist($config);
        }

        match ($section) {
            'produtos' => $config->setProdutos($data),
            'guias' => $config->setGuias($data),
            'integracoes' => $config->setIntegracoes($data),
            'branding' => $config->setBranding($data),
            'planos_limites' => $config->setPlanosLimites($data),
            'onboarding' => $config->setOnboarding($data),
            default => throw new \InvalidArgumentException('Seção clínica inválida.'),
        };

        $this->em->flush();
    }

    private function migrateFromFileIfNeeded(Empresa $empresa, string $section): void
    {
        $path = $this->legacyPath($empresa, $section);
        if ($path === null || !is_file($path)) {
            return;
        }

        $config = $this->repository->findForEmpresa($empresa);
        $current = match ($section) {
            'produtos' => $config?->getProdutos() ?? [],
            'guias' => $config?->getGuias() ?? [],
            'integracoes' => $config?->getIntegracoes() ?? [],
            default => [],
        };
        if ($current !== []) {
            return;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return;
        }
        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return;
        }

        if (!$config instanceof ClinicEmpresaConfig) {
            $config = (new ClinicEmpresaConfig())->setEmpresa($empresa);
            $this->em->persist($config);
        }

        match ($section) {
            'produtos' => $config->setProdutos($decoded),
            'guias' => $config->setGuias($decoded),
            'integracoes' => $config->setIntegracoes($decoded),
            default => null,
        };

        $this->em->flush();
    }

    private function legacyPath(Empresa $empresa, string $section): ?string
    {
        $base = rtrim($this->projectDir, '/\\');
        $id = $empresa->getId();

        return match ($section) {
            'produtos' => sprintf('%s/var/clinic/produtos-%d.json', $base, $id),
            'guias' => sprintf('%s/var/clinic/guias-%d.json', $base, $id),
            'integracoes' => sprintf('%s/var/clinic/integracoes-%d.json', $base, $id),
            default => null,
        };
    }
}
