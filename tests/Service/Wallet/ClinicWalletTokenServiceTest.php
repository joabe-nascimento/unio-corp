<?php

declare(strict_types=1);

namespace App\Tests\Service\Wallet;

use App\Service\Wallet\ClinicWalletTokenService;
use App\Wallet\WalletPassType;
use PHPUnit\Framework\TestCase;

final class ClinicWalletTokenServiceTest extends TestCase
{
    public function testIssueAndResolveRoundTrip(): void
    {
        $service = new ClinicWalletTokenService('test-secret-key');

        $paciente = $this->createMock(\App\Entity\PosOperatorioPaciente::class);
        $paciente->method('getId')->willReturn(42);

        $token = $service->issue($paciente, WalletPassType::Carteirinha, 3600);
        $resolved = $service->resolve($token);

        self::assertNotNull($resolved);
        self::assertSame(42, $resolved['patient_id']);
        self::assertSame(WalletPassType::Carteirinha, $resolved['type']);
    }

    public function testResolveRejectsTamperedToken(): void
    {
        $service = new ClinicWalletTokenService('test-secret-key');
        $paciente = $this->createMock(\App\Entity\PosOperatorioPaciente::class);
        $paciente->method('getId')->willReturn(7);

        $token = $service->issue($paciente, WalletPassType::Comprovante, 3600);
        $tampered = substr($token, 0, -4) . 'XXXX';

        self::assertNull($service->resolve($tampered));
    }
}
