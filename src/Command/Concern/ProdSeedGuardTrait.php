<?php

namespace App\Command\Concern;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ProdSeedGuardTrait
{
    /** Bloqueia em prod sem --allow-prod. Retorna null se pode continuar, ou código de saída. */
    private function refuseInProductionUnlessAllowed(InputInterface $input, SymfonyStyle $io): ?int
    {
        if ($this->appEnv !== 'prod' || $input->getOption('allow-prod')) {
            return null;
        }

        $io->error('Recusado em produção. Comandos de seed/demo não rodam no cron de prod. Use --allow-prod se realmente necessário.');

        return Command::FAILURE;
    }

    private function configureProdSeedGuard(): void
    {
        $this->addOption(
            'allow-prod',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            'Permite execução em produção (use com cautela)'
        );
    }
}
