<?php

namespace App\Command;

use App\Command\Concern\ProdSeedGuardTrait;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\DefaultUserPasswordProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:users:reset-all-passwords',
    description: 'Redefine a senha de todos os usuários para a senha padrão (APP_DEFAULT_USER_PASSWORD ou --password)',
)]
class ResetAllUserPasswordsCommand extends Command
{
    use ProdSeedGuardTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private UserPasswordHasherInterface $hasher,
        private DefaultUserPasswordProvider $defaultPasswordProvider,
        private string $appEnv = 'dev',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configureProdSeedGuard();
        $this
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Senha a aplicar (sobrescreve APP_DEFAULT_USER_PASSWORD)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lista usuários sem alterar o banco')
            ->addOption('include-inactive', null, InputOption::VALUE_NONE, 'Inclui usuários inativos');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($code = $this->refuseInProductionUnlessAllowed($input, $io)) !== null) {
            return $code;
        }

        $password = trim((string) ($input->getOption('password') ?? ''));
        if ($password === '') {
            $password = $this->defaultPasswordProvider->get();
        }

        if ($password === '') {
            $io->error('Informe --password ou defina APP_DEFAULT_USER_PASSWORD no .env.local.');

            return Command::FAILURE;
        }

        if (mb_strlen($password) < 8) {
            $io->error('A senha deve ter pelo menos 8 caracteres.');

            return Command::FAILURE;
        }

        $criteria = $input->getOption('include-inactive') ? [] : ['ativo' => true];
        /** @var list<User> $users */
        $users = $this->userRepo->findBy($criteria, ['email' => 'ASC']);

        if ($users === []) {
            $io->warning('Nenhum usuário encontrado.');

            return Command::SUCCESS;
        }

        $io->title('Redefinir senhas de usuários');
        $io->text(sprintf('Usuários: %d | Ambiente: %s', \count($users), $this->appEnv));

        if ($input->getOption('dry-run')) {
            foreach ($users as $user) {
                $io->text(sprintf(
                    '  %s [%s]%s',
                    $user->getEmail(),
                    $user->getPerfil(),
                    $user->isAtivo() ? '' : ' (inativo)',
                ));
            }
            $io->note('Dry-run: nenhuma senha foi alterada.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('no-interaction') && !$io->confirm(sprintf('Aplicar a nova senha em %d usuário(s)?', \count($users)), false)) {
            $io->warning('Operação cancelada.');

            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $this->em->persist($user);
        }

        $this->em->flush();

        $io->success(sprintf('Senha atualizada para %d usuário(s).', \count($users)));
        $io->text('Use a senha definida em APP_DEFAULT_USER_PASSWORD (ou a passada em --password) no login.');

        return Command::SUCCESS;
    }
}
