<?php

namespace App\Command;

use App\Repository\EmpresaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:patch-empresa-logos', description: 'Atualiza caminhos de logo das empresas sem apagar usuarios')]
class PatchEmpresaLogosCommand extends Command
{
    private const MAP = [
        '11.111.111/0001-11' => 'images/logos/huplex-corp.svg',
        '22.222.222/0001-22' => 'images/logos/nexus-saude.svg',
        '33.333.333/0001-33' => 'images/logos/edu360.svg',
    ];

    public function __construct(
        private EmpresaRepository $empresas,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $n = 0;

        foreach (self::MAP as $cnpj => $logo) {
            $emp = $this->empresas->findOneBy(['cnpj' => $cnpj]);
            if (!$emp) {
                continue;
            }
            $emp->setLogo($logo);
            $n++;
            $io->text($emp->getNome() . ' → ' . $logo);
        }

        $this->em->flush();
        $io->success($n ? "$n logo(s) atualizado(s)." : 'Nenhuma empresa encontrada.');

        return Command::SUCCESS;
    }
}
