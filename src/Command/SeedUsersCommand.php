<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-users', description: 'Cria usuarios de teste para cada perfil')]
class SeedUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = [
            ['nome' => 'Tenant Master',      'email' => 'tenant@huplex.dev',     'perfil' => 'TENANT'],
            ['nome' => 'Admin Silva',         'email' => 'admin@huplex.dev',      'perfil' => 'ADMIN'],
            ['nome' => 'Gestor Oliveira',     'email' => 'gestor@huplex.dev',     'perfil' => 'GESTOR'],
            ['nome' => 'Gestor Costa',        'email' => 'gestor.eq@huplex.dev',  'perfil' => 'GESTOR_EQUIPE'],
            ['nome' => 'Supervisor Geral',    'email' => 'supervisor@huplex.dev', 'perfil' => 'SUPERVISOR'],
            ['nome' => 'Supervisor Equipe',   'email' => 'sup.eq@huplex.dev',     'perfil' => 'SUPERVISOR_EQUIPE'],
            ['nome' => 'Membro Santos',       'email' => 'membro@huplex.dev',     'perfil' => 'MEMBRO'],
        ];

        foreach ($users as $data) {
            $exists = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($exists) {
                $io->note('Ja existe: ' . $data['email']);
                continue;
            }

            $user = new User();
            $user->setNome($data['nome']);
            $user->setEmail($data['email']);
            $user->setPerfil($data['perfil']);
            $user->setRoles([$user->getRolePrincipal()]);
            $user->setPassword($this->hasher->hashPassword($user, 'huplex123'));
            $user->setAtivo(true);
            $this->em->persist($user);
            $io->text('Criado: ' . $data['email'] . ' [' . $data['perfil'] . ']');
        }

        $this->em->flush();
        $io->success('Usuarios criados com senha: huplex123');

        return Command::SUCCESS;
    }
}