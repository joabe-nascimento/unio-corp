<?php

namespace App\Command;

use App\Entity\Empresa;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-users', description: 'Cria empresas e usuarios de teste')]
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

        // Apaga dados existentes
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Empresa e')->execute();

        // Empresas
        $empresas = [];
        foreach ([
            ['nome' => 'Huplex Corp',       'cnpj' => '11.111.111/0001-11', 'setor' => 'Tecnologia'],
            ['nome' => 'Nexus Saude S/A',   'cnpj' => '22.222.222/0001-22', 'setor' => 'Saude'],
            ['nome' => 'Edu360 Ensino',     'cnpj' => '33.333.333/0001-33', 'setor' => 'Educacao'],
        ] as $data) {
            $emp = new Empresa();
            $emp->setNome($data['nome'])->setCnpj($data['cnpj'])->setSetor($data['setor']);
            $this->em->persist($emp);
            $empresas[] = $emp;
        }
        $this->em->flush();
        $io->text('3 empresas criadas.');

        // Usuarios
        $users = [
            ['nome' => 'Tenant Master',      'email' => 'tenant@huplex.dev',     'perfil' => 'TENANT',            'empresa' => null],
            ['nome' => 'Admin Silva',         'email' => 'admin@huplex.dev',      'perfil' => 'ADMIN',             'empresa' => $empresas[0]],
            ['nome' => 'Gestor Oliveira',     'email' => 'gestor@huplex.dev',     'perfil' => 'GESTOR',            'empresa' => $empresas[0]],
            ['nome' => 'Gestor Costa',        'email' => 'gestor.eq@huplex.dev',  'perfil' => 'GESTOR_EQUIPE',     'empresa' => $empresas[0]],
            ['nome' => 'Supervisor Geral',    'email' => 'supervisor@huplex.dev', 'perfil' => 'SUPERVISOR',        'empresa' => $empresas[0]],
            ['nome' => 'Supervisor Equipe',   'email' => 'sup.eq@huplex.dev',     'perfil' => 'SUPERVISOR_EQUIPE', 'empresa' => $empresas[0]],
            ['nome' => 'Membro Santos',       'email' => 'membro@huplex.dev',     'perfil' => 'MEMBRO',            'empresa' => $empresas[0]],
            ['nome' => 'Admin Nexus',         'email' => 'admin@nexus.dev',       'perfil' => 'ADMIN',             'empresa' => $empresas[1]],
            ['nome' => 'Admin Edu360',        'email' => 'admin@edu360.dev',      'perfil' => 'ADMIN',             'empresa' => $empresas[2]],
        ];

        foreach ($users as $data) {
            $user = new User();
            $user->setNome($data['nome'])->setEmail($data['email'])->setPerfil($data['perfil']);
            $user->setRoles([$user->getRolePrincipal()]);
            $user->setPassword($this->hasher->hashPassword($user, 'huplex123'));
            $user->setAtivo(true);
            if ($data['empresa']) $user->setEmpresa($data['empresa']);
            $this->em->persist($user);
            $io->text('  ' . $data['email'] . ' [' . $data['perfil'] . ']');
        }

        $this->em->flush();
        $io->success('Seed concluido. Senha: huplex123');
        return Command::SUCCESS;
    }
}