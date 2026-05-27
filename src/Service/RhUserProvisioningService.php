<?php

namespace App\Service;

use App\Entity\Funcionario;
use App\Entity\RhOnboardingProcess;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RhUserProvisioningService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private UserPasswordHasherInterface $passwordHasher,
        private PlatformConfigService $platformConfig,
    ) {}

    /**
     * Cria usuário da plataforma vinculado ao processo/funcionário.
     */
    public function provisionFromOnboarding(
        RhOnboardingProcess $process,
        string $senha,
        string $perfil = 'MEMBRO',
    ): User {
        if ($process->getStatus() === RhOnboardingProcess::STATUS_CANCELADO) {
            throw new RhProcessException('Processo cancelado.');
        }

        $email = mb_strtolower(trim($process->getEmail()));
        if ($this->userRepo->findOneBy(['email' => $email])) {
            throw new RhProcessException('Já existe um usuário da plataforma com este e-mail.');
        }
        if ($pwdErr = $this->platformConfig->validatePassword($senha)) {
            throw new RhProcessException($pwdErr);
        }

        $user = (new User())
            ->setEmail($email)
            ->setNome($process->getNome())
            ->setPerfil($perfil)
            ->setRoles([$this->rolePorPerfil($perfil)])
            ->setAtivo(true)
            ->setEmpresa($process->getEmpresa());

        $user->setPassword($this->passwordHasher->hashPassword($user, $senha));
        $this->em->persist($user);

        if ($process->getFuncionario()) {
            $process->getFuncionario()->setUser($user);
        }

        $this->markPlataformaChecklistItem($process);
        $this->em->flush();

        return $user;
    }

    public function linkExistingUser(RhOnboardingProcess $process, User $user): void
    {
        if (mb_strtolower($user->getEmail() ?? '') !== mb_strtolower($process->getEmail())) {
            throw new RhProcessException('O e-mail do usuário não coincide com o do processo de admissão.');
        }
        if ($process->getFuncionario()) {
            $process->getFuncionario()->setUser($user);
        }
        $this->markPlataformaChecklistItem($process);
        $this->em->flush();
    }

    public function linkUserToFuncionario(Funcionario $funcionario, User $user): void
    {
        $funcionario->setUser($user);
        $this->em->flush();
    }

    private function markPlataformaChecklistItem(RhOnboardingProcess $process): void
    {
        $checklist = $process->getChecklist();
        foreach ($checklist as &$item) {
            if (($item['id'] ?? '') === 'plataforma') {
                $item['done'] = true;
            }
        }
        unset($item);
        $process->setChecklist($checklist);
        $process->touch();
    }

    private function rolePorPerfil(string $perfil): string
    {
        return match ($perfil) {
            'GESTOR' => User::ROLE_GESTOR,
            'GESTOR_EQUIPE' => User::ROLE_GESTOR_EQUIPE,
            'SUPERVISOR' => User::ROLE_SUPERVISOR,
            'SUPERVISOR_EQUIPE' => User::ROLE_SUPERVISOR_EQUIPE,
            'TENANT' => User::ROLE_TENANT,
            default => User::ROLE_MEMBRO,
        };
    }
}
