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
    name: 'app:user:set-password',
    description: 'Define a senha de um usuário por e-mail (produção: exige --allow-prod)',
)]
final class SetUserPasswordCommand extends Command
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
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-mail do usuário')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Nova senha (mín. 8 caracteres)')
            ->addOption('generate', 'g', InputOption::VALUE_NONE, 'Gera senha aleatória e exibe no final');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($code = $this->refuseInProductionUnlessAllowed($input, $io)) !== null) {
            return $code;
        }

        $email = mb_strtolower(trim((string) $input->getOption('email')));
        if ($email === '') {
            $io->error('Informe --email.');

            return Command::FAILURE;
        }

        $password = trim((string) ($input->getOption('password') ?? ''));
        if ($input->getOption('generate')) {
            $password = bin2hex(random_bytes(6)) . 'Un!o' . random_int(10, 99);
        }

        if ($password === '' || mb_strlen($password) < 8) {
            $io->error('Informe --password (mín. 8) ou use --generate.');

            return Command::FAILURE;
        }

        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $io->error(sprintf('Usuário não encontrado: %s', $email));

            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Senha atualizada para %s [%s].', $email, $user->getPerfil()));
        if ($input->getOption('generate')) {
            $io->writeln('Nova senha: ' . $password);
        }

        return Command::SUCCESS;
    }
}
