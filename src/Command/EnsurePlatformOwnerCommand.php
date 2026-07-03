<?php

namespace App\Command;

use App\Command\Concern\ProdSeedGuardTrait;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:ensure-platform-owner',
    description: 'Cria ou atualiza a conta pessoal PLATFORM_OWNER (acima do tenant, independente de empresa)',
)]
class EnsurePlatformOwnerCommand extends Command
{
    use ProdSeedGuardTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private UserPasswordHasherInterface $hasher,
        private string $appEnv = 'dev',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configureProdSeedGuard();
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-mail da conta pessoal', 'joabenascimento1@outlook.com')
            ->addOption('nome', null, InputOption::VALUE_REQUIRED, 'Nome exibido', 'Joabe Fonseca do Nascimento')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Senha (obrigatória ao criar usuário novo)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($code = $this->refuseInProductionUnlessAllowed($input, $io)) !== null) {
            return $code;
        }

        $email = mb_strtolower(trim((string) $input->getOption('email')));
        $nome = trim((string) $input->getOption('nome'));
        $password = (string) ($input->getOption('password') ?? '');

        if ($email === '' || $nome === '') {
            $io->error('E-mail e nome são obrigatórios.');

            return Command::FAILURE;
        }

        $user = $this->userRepo->findOneBy(['email' => $email]);
        $isNew = $user === null;

        if ($isNew) {
            if ($password === '') {
                $io->error('Informe --password ao criar a conta pela primeira vez.');

                return Command::FAILURE;
            }
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->hasher->hashPassword($user, $password));
        }

        $user
            ->setNome($nome)
            ->setPerfil('PLATFORM_OWNER')
            ->setRoles([User::ROLE_PLATFORM_OWNER])
            ->setAtivo(true)
            ->setEmpresa(null);

        if (!$isNew && $password !== '') {
            $user->setPassword($this->hasher->hashPassword($user, $password));
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf(
            'Conta PLATFORM_OWNER %s (%s). Perfil separado do tenant — acesso global + /admin/operacoes.',
            $isNew ? 'criada' : 'atualizada',
            $email,
        ));

        return Command::SUCCESS;
    }
}
