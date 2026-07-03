<?php

namespace App\Service\PosOperatorio;

final class PosOperatorioMercureTopics
{
    public function __construct(
        private readonly string $topicBase,
    ) {}

    public function empresa(int $empresaId): string
    {
        return rtrim($this->topicBase, '/') . '/empresa/' . $empresaId;
    }

    public function alertas(int $empresaId): string
    {
        return $this->empresa($empresaId) . '/alertas';
    }
}
