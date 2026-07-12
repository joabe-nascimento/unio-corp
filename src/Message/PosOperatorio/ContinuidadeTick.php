<?php

namespace App\Message\PosOperatorio;

final class ContinuidadeTick
{
    public function __construct(
        public readonly ?int $empresaId = null,
    ) {}
}
