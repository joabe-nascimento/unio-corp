<?php

namespace App\Command;

use App\Repository\EmpresaRepository;
use App\Service\Organismo\Runtime\OrganRuntime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:organismo:tick', description: 'Executa o tick do Organismo Runtime (vitais, gêmeo, reflexos)')]
final class OrganismoTickCommand extends Command
{
    public function __construct(
        private OrganRuntime $runtime,
        private EmpresaRepository $empresas,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('empresa', null, InputOption::VALUE_REQUIRED, 'ID da empresa');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->runtime->isClinicRuntime()) {
            $io->warning('Organismo Runtime clínico desligado neste ambiente.');

            return Command::SUCCESS;
        }

        $empresaId = $input->getOption('empresa');
        $list = $empresaId
            ? array_filter([$this->empresas->find((int) $empresaId)])
            : $this->empresas->findBy([], ['id' => 'ASC'], 50);

        $n = 0;
        foreach ($list as $empresa) {
            $state = $this->runtime->tick($empresa, true);
            $io->writeln(sprintf(
                'Empresa #%d %s — score %d (%s), %d cenário(s), %d reflexo(s)',
                $empresa->getId(),
                $empresa->getNome(),
                $state['vitality']['score'],
                $state['vitality']['nivel'],
                \count($state['twin']['scenarios']),
                \count($state['reflexes']),
            ));
            ++$n;
        }

        $io->success(sprintf('Tick concluído em %d empresa(s).', $n));

        return Command::SUCCESS;
    }
}
