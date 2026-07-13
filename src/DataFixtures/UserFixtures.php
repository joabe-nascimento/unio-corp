<?php

namespace App\DataFixtures;

use App\Clinic\ClinicStaffRole;
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
            ['nome' => 'Joabe Nascimento', 'email' => DevSeedEmails::JOABE, 'perfil' => 'TENANT'],
            ['nome' => 'Camila Souza', 'email' => DevSeedEmails::CAMILA_RECEPCAO, 'perfil' => ClinicStaffRole::RECEPCAO],
            ['nome' => 'Beatriz Nunes', 'email' => DevSeedEmails::BEATRIZ_ENFERMAGEM, 'perfil' => ClinicStaffRole::ENFERMAGEM],
            ['nome' => 'André Melo', 'email' => DevSeedEmails::ANDRE_MEDICO, 'perfil' => ClinicStaffRole::MEDICO],
            ['nome' => 'Helena Castro', 'email' => DevSeedEmails::HELENA_COORDENACAO, 'perfil' => ClinicStaffRole::COORDENACAO],
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
