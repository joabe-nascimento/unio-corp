<?php

namespace App\Command;

use App\Service\SystemValidationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:validate-system',
    description: 'Valida regras de permissão, rotas core, workspace e usuários seed',
)]
class ValidateSystemCommand extends Command
{
    public function __construct(
        private SystemValidationService $validation,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Validação do sistema Unio/Huplex');

        $result = $this->validation->validate();

        foreach ($result->reports as $line) {
            $io->writeln('  <fg=green>✓</> ' . $line);
        }

        if ($result->failures !== []) {
            $io->newLine();
            $io->section('Falhas');
            foreach ($result->failures as $failure) {
                $io->writeln('  <fg=red>✗</> ' . $failure);
            }
            $io->error(sprintf('%d falha(s) encontrada(s).', \count($result->failures)));

            return Command::FAILURE;
        }

        $io->success('Sistema validado — regras e infraestrutura OK.');

        return Command::SUCCESS;
    }
}
