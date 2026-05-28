<?php

namespace App\Command;

use App\Repository\UserProductGrantRepository;
use App\Repository\UserRepository;
use App\Security\ProductGrantAccess;
use App\Service\NavigationService;
use App\Service\PermissionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsCommand(name: 'app:test-permission-matrix', description: 'Testa matriz de permissões por usuário seed')]
class TestPermissionMatrixCommand extends Command
{
    public function __construct(
        private UserRepository $userRepo,
        private UserProductGrantRepository $grantRepo,
        private ProductGrantAccess $grants,
        private NavigationService $navigation,
        private PermissionService $permissions,
        private TokenStorageInterface $tokenStorage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach (['membro@unio.dev', 'supervisor@unio.dev', 'gestor@unio.dev'] as $email) {
            $user = $this->userRepo->findOneBy(['email' => $email]);
            if (!$user) {
                continue;
            }

            $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

            $grantCount = (int) $this->grantRepo->createQueryBuilder('g')
                ->select('COUNT(g.id)')
                ->andWhere('g.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            $io->section($user->getNome() . ' (' . $user->getPerfil() . ') — ' . $grantCount . ' grants');

            $checks = [
                'Painel Permissões (global)' => $this->permissions->canManagePermissions($user),
                'Painel Pessoas (escopo)' => $this->permissions->canManagePermissions($user, 'product_pessoas'),
                'Matriz configurada' => $this->grantRepo->userHasConfiguredMatrix($user),
                'Nav Hub Operações' => $this->navigation->showHubOperacoes($user),
                'Nav Pessoas' => $this->navigation->showModuloPessoas($user),
                'Ver membros' => $this->grants->canViewProductForUi($user, 'product_pessoas', 'membros'),
                'Criar membro (>= GESTOR_EQUIPE)' => $this->grants->grantAtLeast($user, 'product_pessoas', 'membros', 'GESTOR_EQUIPE'),
                'Rota hub operacoes' => $this->grants->isRouteAllowed($user, 'app_hub_operacoes'),
                'Rota /pessoas' => $this->grants->isRouteAllowed($user, 'app_pessoas'),
                'Rota novo membro' => $this->grants->isRouteAllowed($user, 'app_pessoas_membro_novo'),
                'Nível grant membros' => $this->grants->effectiveProfileLevel($user, 'product_pessoas', 'membros'),
            ];

            foreach ($checks as $label => $ok) {
                $io->writeln(sprintf('  %-32s %s', $label . ':', \is_bool($ok) ? ($ok ? 'SIM' : 'NÃO') : (string) $ok));
            }
        }

        return Command::SUCCESS;
    }
}
