<?php

declare(strict_types=1);

namespace App\Tests\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\PosOperatorioPortalInviteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PosOperatorioPortalInviteServiceTest extends TestCase
{
    public function testGenerateInvitePersistsToken(): void
    {
        $paciente = (new PosOperatorioPaciente())
            ->setEmpresa(new Empresa())
            ->setCodigo('PO-2001')
            ->setNome('Paciente Teste');

        $repo = $this->createMock(PosOperatorioPacienteRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://clinica.test/clinica/portal/convite/abc123');

        $service = new PosOperatorioPortalInviteService($repo, $em, $urlGenerator);
        $url = $service->generateInvite($paciente);

        self::assertSame('https://clinica.test/clinica/portal/convite/abc123', $url);
        self::assertNotNull($paciente->getPortalInviteToken());
        self::assertNotNull($paciente->getPortalInviteExpiresAt());
    }
}
