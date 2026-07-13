<?php

namespace App\Tests\Service\PosOperatorio\Whatsapp;

use App\Entity\Empresa;
use App\Repository\ClinicOutboundMessageRepository;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappResult;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappSenderInterface;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService;
use App\Service\PosOperatorio\Whatsapp\NoopWhatsappSender;
use PHPUnit\Framework\TestCase;

final class ClinicWhatsappServiceTest extends TestCase
{
    public function testNoopStillLogsSkipped(): void
    {
        $repo = $this->createMock(ClinicOutboundMessageRepository::class);
        $repo->expects(self::once())->method('save');

        $service = new ClinicWhatsappService(new NoopWhatsappSender(), $repo);
        $empresa = (new Empresa())->setNome('Clinica')->setCnpj('00.000.000/0001-00');

        $result = $service->send($empresa, '11999990000', 'Oi', ['event' => 'whatsapp_teste']);

        self::assertFalse($result->sent);
        self::assertSame('skipped', $result->status);
        self::assertFalse($service->isLive());
    }

    public function testLiveSenderPersistsSent(): void
    {
        $sender = new class implements ClinicWhatsappSenderInterface {
            public function isLive(): bool
            {
                return true;
            }

            public function providerName(): string
            {
                return 'meta';
            }

            public function send(Empresa $empresa, string $toE164, string $text, array $context = []): ClinicWhatsappResult
            {
                return ClinicWhatsappResult::sent('meta', 'wamid.X', $toE164);
            }
        };

        $repo = $this->createMock(ClinicOutboundMessageRepository::class);
        $repo->expects(self::once())->method('save');

        $service = new ClinicWhatsappService($sender, $repo);
        $empresa = (new Empresa())->setNome('Clinica')->setCnpj('00.000.000/0001-00');

        $result = $service->send($empresa, '11988887777', 'Confirme', ['event' => 'agenda_confirmacao']);

        self::assertTrue($result->sent);
        self::assertSame('wamid.X', $result->providerMessageId);
        self::assertSame('5511988887777', $service->normalizeE164('11988887777'));
    }
}
