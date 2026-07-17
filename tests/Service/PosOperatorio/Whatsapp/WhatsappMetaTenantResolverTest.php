<?php

namespace App\Tests\Service\PosOperatorio\Whatsapp;

use App\Entity\Empresa;
use App\Repository\EmpresaRepository;
use App\Service\PosOperatorio\Whatsapp\WhatsappMetaTenantResolver;
use PHPUnit\Framework\TestCase;

final class WhatsappMetaTenantResolverTest extends TestCase
{
    public function testResolvesMappedPhoneNumberToExplicitEmpresa(): void
    {
        $empresa = (new Empresa())
            ->setNome('Clínica A')
            ->setCnpj('12345678000190');

        $repository = $this->createMock(EmpresaRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['cnpj' => '12345678000190', 'ativo' => true])
            ->willReturn($empresa);

        $resolver = new WhatsappMetaTenantResolver(
            $repository,
            '',
            '',
            '{"phone-a":"12345678000190"}',
        );

        self::assertSame($empresa, $resolver->resolve('phone-a'));
    }

    public function testUnknownPhoneNumberNeverFallsBackToFirstEmpresa(): void
    {
        $repository = $this->createMock(EmpresaRepository::class);
        $repository->expects(self::never())->method('findOneBy');
        $repository->expects(self::never())->method('findBy');

        $resolver = new WhatsappMetaTenantResolver(
            $repository,
            'configured-phone',
            '12345678000190',
            '{"another-phone":"00999999000100"}',
        );

        self::assertNull($resolver->resolve('unknown-phone'));
    }

    public function testSingleTenantRequiresMatchingPhoneAndCnpj(): void
    {
        $empresa = (new Empresa())
            ->setNome('Clínica Única')
            ->setCnpj('12345678000190');

        $repository = $this->createMock(EmpresaRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['cnpj' => '12345678000190', 'ativo' => true])
            ->willReturn($empresa);

        $resolver = new WhatsappMetaTenantResolver(
            $repository,
            'phone-single',
            '12.345.678/0001-90',
        );

        self::assertSame($empresa, $resolver->resolve('phone-single'));
    }
}
