<?php

namespace App\Tests\Service;

use App\Entity\Empresa;
use App\Entity\RhOnboardingProcess;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\RhOnboardingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RhOnboardingServiceTest extends TestCase
{
    public function testCompleteRequiresFullChecklist(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(RhOnboardingProcessRepository::class);
        $funcRepo = $this->createMock(FuncionarioRepository::class);
        $funcRepo->method('existsByEmail')->willReturn(false);

        $service = new RhOnboardingService($em, $repo, $funcRepo);

        $empresa = new Empresa();
        $process = new RhOnboardingProcess();
        $process->setEmpresa($empresa);
        $process->setNome('Test');
        $process->setEmail('test@example.com');
        $process->setStatus(RhOnboardingProcess::STATUS_EM_ANDAMENTO);
        $checklist = RhOnboardingProcess::defaultChecklist();
        $checklist[0]['done'] = true;
        $process->setChecklist($checklist);

        $this->expectException(RhProcessException::class);
        $service->complete($process);
    }
}
