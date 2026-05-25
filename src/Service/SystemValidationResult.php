<?php

namespace App\Service;

final class SystemValidationResult
{
    /** @param list<string> $failures */
    public function __construct(
        public readonly bool $ok,
        public readonly array $failures = [],
        public readonly array $reports = [],
    ) {}

    public static function pass(array $reports = []): self
    {
        return new self(true, [], $reports);
    }

    /** @param list<string> $failures */
    public static function fail(array $failures, array $reports = []): self
    {
        return new self(false, $failures, $reports);
    }
}
