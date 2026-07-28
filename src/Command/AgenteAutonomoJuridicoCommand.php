<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Juridico\AgenteAutonomoJuridicoService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Coração do "Agente Autônomo 24/7": roda periodicamente (cron/systemd timer, a cada
 * 30 min) varrendo prazos, tarefas e carteira de todos os escritórios e notificando
 * quem precisa agir — sem esperar ninguém abrir o chat da Bruna.
 */
#[AsCommand(
    name: 'app:juridico:agente-autonomo',
    description: 'Agente autônomo jurídico: varre prazos/tarefas/carteira e notifica proativamente (cron a cada 30 min)',
)]
final class AgenteAutonomoJuridicoCommand extends Command
{
    public function __construct(
        private AgenteAutonomoJuridicoService $agente,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('empresa-id', null, InputOption::VALUE_OPTIONAL, 'Limitar a uma empresa');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $empresaId = $input->getOption('empresa-id');

        $resultado = $this->agente->executar($empresaId !== null ? (int) $empresaId : null);

        if (!$resultado['executado']) {
            $io->warning('Agente autônomo desligado neste ambiente (perfil não é jurídico).');

            return Command::SUCCESS;
        }

        foreach ($resultado['detalhes'] as $detalhe) {
            $io->writeln(sprintf('%s — %d alerta(s) novo(s)', $detalhe['empresa'], $detalhe['alertas']));
        }

        $io->success(sprintf(
            'Agente autônomo — %d escritório(s) verificado(s), %d alerta(s) novo(s) enviado(s).',
            $resultado['empresas_processadas'],
            $resultado['alertas_gerados'],
        ));

        return Command::SUCCESS;
    }
}
