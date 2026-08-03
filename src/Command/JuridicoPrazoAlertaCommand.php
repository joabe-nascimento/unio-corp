<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\EmpresaRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Service\Juridico\JuridicoPrazoAlertaService;
use App\Service\Organismo\OrganismoCopyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:juridico:prazo-alertas',
    description: 'Dispara alertas de prazo em cascata (D-7, D-3, D-1, hoje, vencido) via WhatsApp e e-mail',
)]
final class JuridicoPrazoAlertaCommand extends Command
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private EmpresaRepository $empresaRepo,
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoPrazoAlertaService $alertas,
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

        if (!$this->organismoCopy->isJuridicoProfile()) {
            $io->note('Perfil não jurídico — comando ignorado.');

            return Command::SUCCESS;
        }

        $empresaId = $input->getOption('empresa-id');
        $empresas = $empresaId !== null
            ? array_filter([$this->empresaRepo->find((int) $empresaId)])
            : $this->empresaRepo->findBy(['ativo' => true]);

        $total = 0;
        foreach ($empresas as $empresa) {
            $prazos = $this->prazoRepo->findForEmpresa($empresa, 'pendentes');
            $enviados = $this->alertas->processarEmpresa($empresa, $prazos);
            $total += $enviados;
            $io->writeln(sprintf('  %s: %d canal(is) de alerta', $empresa->getNome(), $enviados));
        }

        $io->success(sprintf('Alertas de prazo processados — %d envio(s) de canal.', $total));

        return Command::SUCCESS;
    }
}
