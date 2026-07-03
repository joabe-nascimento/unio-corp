<?php

namespace App\Config;

/**
 * Matriz config-driven: evento de domínio → notificação in-app (e futuros canais).
 *
 * @phpstan-type NotificationPolicy array{
 *     modulo: string,
 *     tipo: string,
 *     titulo_template: string,
 *     severidade: string,
 *     icon: string,
 *     route: string|null,
 * }
 */
final class NotificationPolicyMatrix
{
    /** @var array<string, NotificationPolicy> eventName => policy */
    private const POLICIES = [
        'pos_operatorio.alerta_gerado' => [
            'modulo' => 'pos_operatorio',
            'tipo' => 'alerta_clinico',
            'titulo_template' => 'Alerta {prioridade} — {codigo}',
            'severidade' => 'dynamic',
            'icon' => 'fa-triangle-exclamation',
            'route' => 'app_pos_operatorio_alertas',
        ],
        'pos_operatorio.questionario_submetido' => [
            'modulo' => 'pos_operatorio',
            'tipo' => 'questionario',
            'titulo_template' => 'Questionário — {codigo}',
            'severidade' => 'info',
            'icon' => 'fa-file-medical',
            'route' => 'app_pos_operatorio',
        ],
    ];

    /** @return NotificationPolicy|null */
    public static function forEvent(string $eventName): ?array
    {
        return self::POLICIES[$eventName] ?? null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{modulo: string, tipo: string, titulo: string, severidade: string, icon: string, route: string|null}
     */
    public static function resolveNotification(string $eventName, array $payload): ?array
    {
        $policy = self::forEvent($eventName);
        if ($policy === null) {
            return null;
        }

        $titulo = $policy['titulo_template'];
        foreach ($payload as $key => $value) {
            if (\is_string($value) || is_numeric($value)) {
                $titulo = str_replace('{' . $key . '}', (string) $value, $titulo);
            }
        }

        $severidade = $policy['severidade'];
        if ($severidade === 'dynamic') {
            $severidade = match ($payload['prioridade'] ?? '') {
                'P1' => 'danger',
                'P2' => 'warning',
                default => 'info',
            };
        }

        return [
            'modulo' => $policy['modulo'],
            'tipo' => $policy['tipo'],
            'titulo' => $titulo,
            'severidade' => $severidade,
            'icon' => $policy['icon'],
            'route' => $policy['route'],
        ];
    }
}
