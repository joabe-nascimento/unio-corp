<?php

namespace App\Command;

use App\Command\Concern\ProdSeedGuardTrait;
use App\Entity\Empresa;
use App\Repository\EmpresaRepository;
use App\Repository\UserRepository;
use App\Service\Clinic\ClinicSandboxService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clinic:sandbox-seed',
    description: 'Cria beneficiários sandbox (Ana Costa, João Pereira, Maria Silva) com carteirinhas demo',
)]
final class ClinicSandboxSeedCommand extends Command
{
    use ProdSeedGuardTrait;

    public function __construct(
        private EmpresaRepository $empresaRepo,
        private UserRepository $userRepo,
        private ClinicSandboxService $sandbox,
        private string $appEnv = 'dev',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configureProdSeedGuard();
        $this->addOption('empresa-id', null, InputOption::VALUE_REQUIRED, 'ID da empresa');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($code = $this->refuseInProductionUnlessAllowed($input, $io)) !== null) {
            return $code;
        }

        $empresaId = $input->getOption('empresa-id');
        $empresa = $empresaId
            ? $this->empresaRepo->find((int) $empresaId)
            : $this->empresaRepo->findOneBy([], ['id' => 'ASC']);

        if (!$empresa instanceof Empresa) {
            $io->error('Empresa não encontrada.');

            return Command::FAILURE;
        }

        $autor = $this->userRepo->findOneBy([], ['id' => 'ASC']);
        if ($autor === null) {
            $io->error('Nenhum usuário no sistema.');

            return Command::FAILURE;
        }

        $patients = $this->sandbox->ensureSandbox($empresa, $autor);

        $io->success(sprintf('Sandbox OK — %d beneficiário(s): %s', \count($patients), implode(', ', array_map(
            static fn ($p) => $p->getCodigo() . ' (' . $p->getNome() . ')',
            $patients,
        ))));

        return Command::SUCCESS;
    }
}
