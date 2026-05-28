<?php

namespace App\Command;

use App\Entity\DevMeta;
use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Repository\EmpresaRepository;
use App\Service\DevProjetoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-dev-projetos', description: 'Demo: projetos de desenvolvimento Unio')]
class SeedDevProjetosCommand extends Command
{
    public function __construct(
        private EmpresaRepository $empresaRepo,
        private DevProjetoService $service,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fresh', null, InputOption::VALUE_NONE, 'Recria dados demo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $empresa = $this->empresaRepo->findOneBy(['nome' => SeedUsersCommand::DEMO_EMPRESA_NOME])
            ?? $this->empresaRepo->findOneBy([], ['id' => 'ASC']);

        if (!$empresa) {
            $io->error('Execute app:seed-users antes.');

            return Command::FAILURE;
        }

        if ($input->getOption('fresh')) {
            $this->em->createQuery('DELETE FROM App\Entity\DevTarefa t WHERE t.empresa = :e')->setParameter('e', $empresa)->execute();
            $this->em->createQuery('DELETE FROM App\Entity\DevMeta m WHERE m.empresa = :e')->setParameter('e', $empresa)->execute();
            $this->em->createQuery('DELETE FROM App\Entity\DevProjeto p WHERE p.empresa = :e')->setParameter('e', $empresa)->execute();
        } elseif ($this->em->getRepository(DevProjeto::class)->findOneBy(['codigo' => 'UNIO-HUB-OPS', 'empresa' => $empresa])) {
            $io->warning('Demo já existe. Use --fresh.');

            return Command::SUCCESS;
        }

        $hub = $this->service->createProjeto(
            $empresa,
            'Hub Operações — layout e navegação',
            'UNIO-HUB-OPS',
            'Ajustar page_list, sidebar, cards do hub e consistência visual.',
            'Hub Operações',
            DevProjeto::STATUS_EM_ANDAMENTO,
            '#4F7FFF',
            new \DateTimeImmutable('+30 days'),
        );

        $auth = $this->service->createProjeto(
            $empresa,
            'Login, cadastro e recuperação de senha',
            'UNIO-AUTH',
            'Fluxo auth Unio, Mailpit, conta pendente.',
            'Core / Auth',
            DevProjeto::STATUS_EM_ANDAMENTO,
            '#5dd49b',
            new \DateTimeImmutable('+14 days'),
        );

        $rh = $this->service->createProjeto(
            $empresa,
            'RH — onboarding e offboarding',
            'UNIO-RH',
            'Admissões, demissões, checklist.',
            'Módulo RH',
            DevProjeto::STATUS_FEITO,
            '#e0c044',
            new \DateTimeImmutable('-7 days'),
        );

        $meta = $this->service->createMeta(
            $empresa,
            $hub,
            'Sidebar e hub cards alinhados ao design system',
            'Remover textos pretos, table-unio, tokens de cor.',
            DevMeta::STATUS_EM_ANDAMENTO,
            'ALTA',
            40,
            new \DateTimeImmutable('+10 days'),
        );

        $this->service->createTarefa($hub, 'Revisar page_list em todos os módulos', null, DevTarefa::STATUS_EM_ANDAMENTO, 'ALTA', $meta);
        $this->service->createTarefa($hub, 'Card RH / Pessoas / Eng no hub Operações', null, DevTarefa::STATUS_A_FAZER, 'MEDIA');
        $this->service->createTarefa($hub, 'KPIs reais no dashboard Operações', null, DevTarefa::STATUS_BACKLOG, 'BAIXA');
        $this->service->createTarefa($hub, 'Documentar padrão de list_toolbar', 'README interno', DevTarefa::STATUS_CONCLUIDO, 'MEDIA');

        $this->service->createTarefa($auth, 'Tabs login / cadastro', null, DevTarefa::STATUS_CONCLUIDO, 'ALTA');
        $this->service->createTarefa($auth, 'Esqueci senha + Mailpit', null, DevTarefa::STATUS_EM_ANDAMENTO, 'ALTA');
        $this->service->createTarefa($auth, 'AppUserChecker conta pendente', null, DevTarefa::STATUS_A_FAZER, 'MEDIA');

        $this->service->createTarefa($rh, 'Componentizar admissões/demissões', null, DevTarefa::STATUS_CONCLUIDO, 'ALTA');
        $this->service->createTarefa($rh, 'Merge product/core', null, DevTarefa::STATUS_CONCLUIDO, 'MEDIA');

        $io->success('Demo em /core/projetos — login gestor@unio.dev, workspace Unio Demo.');

        return Command::SUCCESS;
    }
}
