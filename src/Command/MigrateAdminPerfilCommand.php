<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-admin-perfil',
    description: 'Converte usuários com perfil ADMIN legado para GESTOR (exceto TENANT)'
)]
class MigrateAdminPerfilCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $users = $this->em->getRepository(User::class)->findBy(['perfil' => 'ADMIN']);
        $n = 0;

        foreach ($users as $user) {
            $user->setPerfil('GESTOR');
            $user->setRoles([$user->getRolePrincipal()]);
            $io->text($user->getEmail() . ' → GESTOR');
            $n++;
        }

        $this->em->flush();
        $io->success($n ? "$n usuário(s) migrado(s)." : 'Nenhum perfil ADMIN no banco.');

        return Command::SUCCESS;
    }
}
