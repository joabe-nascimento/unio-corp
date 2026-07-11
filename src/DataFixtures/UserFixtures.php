<?php

namespace App\DataFixtures;

use App\Dev\DevSeedEmails;
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
            ['nome' => 'Joabe Nascimento',    'email' => DevSeedEmails::JOABE,     'perfil' => 'TENANT'],
            ['nome' => 'Renata Oliveira',     'email' => DevSeedEmails::RENATA,    'perfil' => 'GESTOR'],
            ['nome' => 'Ricardo Costa',       'email' => DevSeedEmails::RICARDO,   'perfil' => 'GESTOR_EQUIPE'],
            ['nome' => 'Ana Paula Ribeiro',   'email' => DevSeedEmails::ANA_PAULA, 'perfil' => 'SUPERVISOR'],
            ['nome' => 'Felipe Martins',      'email' => DevSeedEmails::FELIPE,    'perfil' => 'SUPERVISOR_EQUIPE'],
            ['nome' => 'Lucas Santos',        'email' => DevSeedEmails::LUCAS,     'perfil' => 'MEMBRO'],
        ];

        foreach ($users as $data) {
            $user = new User();
            $user->setNome($data['nome']);
            $user->setEmail($data['email']);
            $user->setPerfil($data['perfil']);
            $user->setRoles([$user->getRolePrincipal()]);
            $user->setPassword($this->hasher->hashPassword($user, 'unio123'));
            $user->setAtivo(true);
            $manager->persist($user);
        }

        $manager->flush();
    }
}
