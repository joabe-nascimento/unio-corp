<?php

namespace App\Tests\Service;

use App\Rh\RhCandidatoOrigem;
use PHPUnit\Framework\TestCase;

class RhRecrutamentoOrigemTest extends TestCase
{
    public function testOrigemLabelsAndValidation(): void
    {
        self::assertSame('LinkedIn', RhCandidatoOrigem::label(RhCandidatoOrigem::LINKEDIN));
        self::assertTrue(RhCandidatoOrigem::isValid(RhCandidatoOrigem::INDICACAO));
        self::assertFalse(RhCandidatoOrigem::isValid('INVALID'));
        self::assertCount(\count(RhCandidatoOrigem::ALL), RhCandidatoOrigem::options());
    }
}
