<?php

namespace App\Service\Vitoria\Tool\Juridico;

/**
 * Padrão comum das ferramentas que escrevem no sistema: nada é gravado no primeiro
 * clique. A ferramenta sempre devolve uma "prévia" com o que pretende fazer, e só
 * executa de verdade quando o usuário confirma explicitamente (`confirmado: true`
 * nos parâmetros) — o mesmo contrato para qualquer ação irreversível da Bruna.
 */
trait ConfirmableToolTrait
{
    /** @param array<string, mixed> $params */
    private function confirmado(array $params): bool
    {
        $v = $params['confirmado'] ?? false;

        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    /**
     * @param array<string, mixed> $params
     * @param list<array{label: string, value: string}> $preview
     *
     * @return array{type: string, tool: string, title: string, preview: array, confirm_label: string, cancel_label: string, params: array}
     */
    private function pedirConfirmacao(
        string $tool,
        array $params,
        string $titulo,
        array $preview,
        string $confirmLabel = 'Sim, pode confirmar',
        string $cancelLabel = 'Agora não',
    ): array {
        return [
            'type' => 'confirm',
            'tool' => $tool,
            'title' => $titulo,
            'preview' => $preview,
            'confirm_label' => $confirmLabel,
            'cancel_label' => $cancelLabel,
            'params' => array_merge($params, ['confirmado' => true]),
        ];
    }
}
