<?php

namespace App\Command;

use App\Command\Concern\ProdSeedGuardTrait;
use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Repository\EmpresaRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\RhOffboardingService;
use App\Service\RhOnboardingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-rh-processes',
    description: 'Cria processos de admissão e demissão de demonstração (Unio Demo)',
)]
class SeedRhProcessesCommand extends Command
{
    use ProdSeedGuardTrait;

    public function __construct(
        private EmpresaRepository $empresaRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private RhOnboardingService $onboarding,
        private RhOffboardingService $offboarding,
        private EntityManagerInterface $em,
        private string $appEnv = 'dev',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configureProdSeedGuard();
        $this->addOption('fresh', null, InputOption::VALUE_NONE, 'Remove processos RH existentes da empresa antes de criar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($code = $this->refuseInProductionUnlessAllowed($input, $io)) !== null) {
            return $code;
        }

        $empresa = $this->empresaRepo->findOneBy(['nome' => SeedUsersCommand::DEMO_EMPRESA_NOME])
            ?? $this->empresaRepo->findOneBy([], ['id' => 'ASC']);

        if (!$empresa) {
            $io->error('Nenhuma empresa encontrada. Execute app:seed-users antes.');

            return Command::FAILURE;
        }

        if ($input->getOption('fresh')) {
            $this->clearRhData($empresa);
            $io->text('Processos RH anteriores removidos.');
        } elseif ($this->onboardingRepo->findOneBy(['email' => 'maria.fernandes.demo@unio.dev', 'empresa' => $empresa])) {
            $io->warning(sprintf(
                'Dados demo RH já existem para "%s". Use --fresh para recriar.',
                $empresa->getNome()
            ));

            return Command::SUCCESS;
        }

        $inicio = new \DateTimeImmutable('+7 days');

        $maria = $this->onboarding->create(
            $empresa,
            'Maria Fernandes',
            'maria.fernandes.demo@unio.dev',
            'Analista de RH',
            $inicio,
            'Onboarding de demonstração — documentação em andamento.',
        );
        $this->markChecklistDone($maria, ['docs', 'ti']);
        $io->text('  Admissão: Maria Fernandes (50% checklist)');

        $this->onboarding->create(
            $empresa,
            'Lucas Pereira',
            'lucas.pereira.demo@unio.dev',
            'Desenvolvedor Back-end',
            $inicio->modify('+14 days'),
            'Aguardando início — processo recém-aberto.',
        );
        $io->text('  Admissão: Lucas Pereira (0% checklist)');

        $carlos = $this->onboarding->create(
            $empresa,
            'Carlos Mendes',
            'carlos.mendes.demo@unio.dev',
            'Coordenador de Operações',
            new \DateTimeImmutable('-30 days'),
            'Concluído para servir de base ao offboarding demo.',
        );
        foreach (array_column($carlos->getChecklist(), 'id') as $itemId) {
            $this->onboarding->toggleChecklistItem($carlos, $itemId, true);
        }
        $this->onboarding->complete($carlos);
        $io->text('  Admissão concluída → funcionário Carlos Mendes');

        $ana = new Funcionario();
        $ana->setEmpresa($empresa);
        $ana->setNome('Ana Costa');
        $ana->setEmail('ana.costa.demo@unio.dev');
        $ana->setCargo('Designer UX');
        $ana->setDataAdmissao(new \DateTimeImmutable('-180 days'));
        $ana->setStatus('ATIVO');
        $this->em->persist($ana);
        $this->em->flush();
        $io->text('  Funcionário ativo: Ana Costa');

        $off = $this->offboarding->create(
            $empresa,
            $ana,
            new \DateTimeImmutable('+3 days'),
            'Pedido de demissão',
            'Offboarding de demonstração.',
        );
        $this->markChecklistDone($off, ['acessos']);
        $io->text('  Demissão: Ana Costa (25% checklist)');

        $io->success(sprintf(
            'Seed RH concluído para "%s". Acesse Operações → RH → Admissões / Demissões.',
            $empresa->getNome()
        ));

        return Command::SUCCESS;
    }

    private function clearRhData(Empresa $empresa): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\RhOnboardingProcess p WHERE p.empresa = :e')
            ->setParameter('e', $empresa)
            ->execute();
        $this->em->createQuery('DELETE FROM App\Entity\RhOffboardingProcess p WHERE p.empresa = :e')
            ->setParameter('e', $empresa)
            ->execute();
        $this->em->createQuery(
            'DELETE FROM App\Entity\Funcionario f WHERE f.empresa = :e AND f.email LIKE :demo'
        )
            ->setParameter('e', $empresa)
            ->setParameter('demo', '%.demo@unio.dev')
            ->execute();
    }

    private function markChecklistDone(RhOnboardingProcess|RhOffboardingProcess $process, array $itemIds): void
    {
        $service = $process instanceof RhOnboardingProcess ? $this->onboarding : $this->offboarding;
        foreach ($itemIds as $id) {
            $service->toggleChecklistItem($process, $id, true);
        }
    }
}
