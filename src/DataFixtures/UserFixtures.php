<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        $users = [
            ['nome' => 'Tenant Master',       'email' => 'tenant@huplex.dev',     'perfil' => 'TENANT'],
            ['nome' => 'Admin Silva',          'email' => 'admin@huplex.dev',      'perfil' => 'ADMIN'],
            ['nome' => 'Gestor Oliveira',      'email' => 'gestor@huplex.dev',     'perfil' => 'GESTOR'],
            ['nome' => 'Gestor Equipe Costa',  'email' => 'gestor.eq@huplex.dev',  'perfil' => 'GESTOR_EQUIPE'],
            ['nome' => 'Supervisor Geral',     'email' => 'supervisor@huplex.dev', 'perfil' => 'SUPERVISOR'],
            ['nome' => 'Supervisor Equipe',    'email' => 'sup.eq@huplex.dev',     'perfil' => 'SUPERVISOR_EQUIPE'],
            ['nome' => 'Membro Santos',        'email' => 'membro@huplex.dev',     'perfil' => 'MEMBRO'],
        ];

        foreach ($users as $data) {
            $user = new User();
            $user->setNome($data['nome']);
            $user->setEmail($data['email']);
            $user->setPerfil($data['perfil']);
            $user->setRoles([$user->getRolePrincipal()]);
            $user->setPassword($this->hasher->hashPassword($user, 'huplex123'));
            $user->setAtivo(true);
            $manager->persist($user);
        }

        $manager->flush();
    }
}