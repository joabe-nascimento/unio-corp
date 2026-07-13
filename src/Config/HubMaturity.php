<?php

namespace App\Config;

/**
 * Níveis de maturidade por núcleo — alinha produto, vendas e desenvolvimento.
 */
final class HubMaturity
{
    public const PREVIEW = 'preview';
    public const MVP = 'mvp';
    public const OPERATIONAL = 'operational';
    public const MATURE = 'mature';

    /** @var array<string, string> hub id => level */
    public const LEVELS = [
        // Núcleos operacionais
        'operacoes' => self::OPERATIONAL,
        'rh' => self::MATURE,
        'pessoas' => self::OPERATIONAL,
        'ti' => self::MATURE,
        'recrutamento' => self::OPERATIONAL,
        'talentos' => self::MVP,
        'maturidade' => self::MVP,
        'integracoes' => self::OPERATIONAL,
        'cortex' => self::OPERATIONAL,
        'admin' => self::MATURE,
        // Pós-Operatório Fase 1.5
        'pos_operatorio' => self::MVP,
        // Demais planejados
        'comercial' => self::MVP,
        'beneficios' => self::PREVIEW,
        'saude_ocupacional' => self::PREVIEW,
        'sst' => self::PREVIEW,
        'qualidade' => self::PREVIEW,
        'obras' => self::PREVIEW,
        'publicidade' => self::PREVIEW,
    ];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::PREVIEW => 'Preview',
            self::MVP => 'MVP',
            self::OPERATIONAL => 'Operacional',
            self::MATURE => 'Maduro',
        ];
    }

    public static function levelFor(string $hubId): string
    {
        return self::LEVELS[$hubId] ?? self::PREVIEW;
    }

    public static function labelFor(string $level): string
    {
        return self::labels()[$level] ?? 'Preview';
    }

    public static function badgeVariant(string $level): string
    {
        return match ($level) {
            self::MATURE => 'success',
            self::OPERATIONAL => 'info',
            self::MVP => 'warning',
            default => 'default',
        };
    }

    /**
     * @param array<string, mixed> $hub
     *
     * @return array<string, mixed>
     */
    public static function enrichHub(array $hub): array
    {
        $level = $hub['maturity_level'] ?? self::levelFor($hub['id'] ?? '');

        return array_merge($hub, [
            'maturity_level' => $level,
            'maturity_label' => self::labelFor($level),
            'maturity_badge' => self::badgeVariant($level),
        ]);
    }
}
