<?php

declare(strict_types=1);

namespace App\Tests\Service\Marketing;

use App\Service\Marketing\ClinicPatientProductService;
use PHPUnit\Framework\TestCase;

final class ComprovanteDemoCardTest extends TestCase
{
    public function testComprovanteDemoCardUsaFormatoDoFlipCard(): void
    {
        $card = (new ClinicPatientProductService())->comprovanteDemoCard();

        self::assertSame('Comprovante', $card['doc_type_label']);
        self::assertSame('Comprovante', $card['ribbon']);
        self::assertSame('profissional', $card['theme']);
        self::assertSame('PO-0042', $card['codigo']);
        self::assertNotEmpty($card['verificacao']);
        self::assertSame('Documento do procedimento', $card['role']);
    }
}
