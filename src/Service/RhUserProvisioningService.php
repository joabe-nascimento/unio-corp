<?php

namespace App\Service;

use App\Entity\Funcionario;
use App\Entity\RhOnboardingProcess;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RhUserProvisioningService
{
    private const ASSIGNABLE_PERFIS = ['MEMBRO', 'SUPERVISOR_EQUIPE', 'GESTOR_EQUIPE'];

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private FuncionarioRepository $funcionarioRepo,
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
        $this->assertProcessNotCancelled($process);
        if ($process->getStatus() === RhOnboardingProcess::STATUS_CONCLUIDO) {
            throw new RhProcessException('Onboarding já concluído. Vincule a conta na ficha do funcionário.');
        }

        $email = $this->normalizeEmail($process->getEmail());
        if ($this->userRepo->findOneBy(['email' => $email])) {
            throw new RhProcessException('Já existe um usuário da plataforma com este e-mail. Use “Vincular conta existente”.');
        }
        if ($pwdErr = $this->platformConfig->validatePassword($senha)) {
            throw new RhProcessException($pwdErr);
        }

        $perfil = $this->normalizeAssignablePerfil($perfil);

        $user = (new User())
            ->setEmail($email)
            ->setNome($process->getNome())
            ->setPerfil($perfil)
            ->setRoles([$this->rolePorPerfil($perfil)])
            ->setAtivo(true)
            ->setEmpresa($process->getEmpresa());

        $user->setPassword($this->passwordHasher->hashPassword($user, $senha));
        $this->em->persist($user);

        $this->linkUserToProcessFuncionario($process, $user);
        $this->markPlataformaChecklistItem($process);
        $this->em->flush();

        return $user;
    }

    /**
     * Vincula conta já cadastrada (ex.: auto-registro) ao processo de admissão.
     */
    public function linkExistingUserFromOnboarding(
        RhOnboardingProcess $process,
        string $perfil = 'MEMBRO',
    ): User {
        $this->assertProcessNotCancelled($process);

        $email = $this->normalizeEmail($process->getEmail());
        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            throw new RhProcessException('Não há conta cadastrada com este e-mail.');
        }

        $this->linkExistingUser($process, $user, $perfil);

        return $user;
    }

    /**
     * Vincula conta existente diretamente na ficha do funcionário.
     */
    public function linkExistingUserForFuncionario(
        Funcionario $funcionario,
        string $perfil = 'MEMBRO',
    ): User {
        $email = $this->normalizeEmail($funcionario->getEmail());
        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            throw new RhProcessException('Não há conta cadastrada com o e-mail deste funcionário.');
        }

        $this->assertUserLinkableForFuncionario($funcionario, $user);

        $perfil = $this->normalizeAssignablePerfil($perfil);
        $empresa = $funcionario->getEmpresa();

        if ($user->getEmpresa() === null && $empresa !== null) {
            $user->setEmpresa($empresa);
        }

        $user->setPerfil($perfil);
        $user->setRoles([$this->rolePorPerfil($perfil)]);

        if (!$user->isAtivo()) {
            $user->setAtivo(true);
        }

        $funcionario->setUser($user);
        $this->em->flush();

        return $user;
    }

    public function linkExistingUser(
        RhOnboardingProcess $process,
        User $user,
        string $perfil = 'MEMBRO',
    ): void {
        $this->assertProcessNotCancelled($process);
        $this->assertUserLinkable($process, $user);

        $this->applyUserLink($user, $process->getEmpresa(), $perfil);
        $this->linkUserToProcessFuncionario($process, $user);
        $this->markPlataformaChecklistItem($process);
        $this->em->flush();
    }

    public function linkUserToFuncionario(Funcionario $funcionario, User $user): void
    {
        $funcionario->setUser($user);
        $this->em->flush();
    }

    /**
     * Estado da conta na plataforma para a UI da admissão.
     *
     * @return array{state: string, user: ?User, reason: ?string}
     */
    public function resolvePlatformAccountState(RhOnboardingProcess $process): array
    {
        if ($this->isPlatformActuallyLinked($process)) {
            return [
                'state' => 'linked',
                'user' => $process->getFuncionario()?->getUser(),
                'reason' => null,
            ];
        }

        return $this->resolvePlatformAccountStateByEmail(
            $this->normalizeEmail($process->getEmail()),
            $process->getEmpresa(),
            $process->getFuncionario(),
        );
    }

    /**
     * Estado da conta na plataforma para a ficha do funcionário.
     *
     * @return array{state: string, user: ?User, reason: ?string}
     */
    public function resolvePlatformAccountStateForFuncionario(Funcionario $funcionario): array
    {
        $linkedUser = $funcionario->getUser();
        if ($linkedUser !== null && $linkedUser->getEmpresa() !== null) {
            return ['state' => 'linked', 'user' => $linkedUser, 'reason' => null];
        }

        return $this->resolvePlatformAccountStateByEmail(
            $this->normalizeEmail($funcionario->getEmail()),
            $funcionario->getEmpresa(),
            $funcionario,
        );
    }

    /**
     * @return array{state: string, user: ?User, reason: ?string}
     */
    private function resolvePlatformAccountStateByEmail(
        string $email,
        ?\App\Entity\Empresa $empresa,
        ?Funcionario $funcionario,
    ): array {
        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            return ['state' => 'create', 'user' => null, 'reason' => null];
        }

        try {
            if ($funcionario !== null) {
                $this->assertUserLinkableForFuncionario($funcionario, $user);
            } elseif ($empresa !== null) {
                $this->assertUserLinkableForEmpresa($empresa, $user, null);
            }

            return ['state' => 'link', 'user' => $user, 'reason' => null];
        } catch (RhProcessException $e) {
            return ['state' => 'blocked', 'user' => $user, 'reason' => $e->getMessage()];
        }
    }

    private function assertProcessNotCancelled(RhOnboardingProcess $process): void
    {
        if ($process->getStatus() === RhOnboardingProcess::STATUS_CANCELADO) {
            throw new RhProcessException('Processo cancelado.');
        }
    }

    private function assertUserLinkable(RhOnboardingProcess $process, User $user): void
    {
        if ($this->normalizeEmail($user->getEmail() ?? '') !== $this->normalizeEmail($process->getEmail())) {
            throw new RhProcessException('O e-mail do usuário não coincide com o do processo de admissão.');
        }

        $this->assertUserLinkableForEmpresa(
            $process->getEmpresa(),
            $user,
            $process->getFuncionario(),
        );
    }

    private function assertUserLinkableForFuncionario(Funcionario $funcionario, User $user): void
    {
        if ($this->normalizeEmail($user->getEmail() ?? '') !== $this->normalizeEmail($funcionario->getEmail())) {
            throw new RhProcessException('O e-mail da conta não coincide com o do funcionário.');
        }

        $this->assertUserLinkableForEmpresa(
            $funcionario->getEmpresa(),
            $user,
            $funcionario,
        );
    }

    private function assertUserLinkableForEmpresa(
        ?\App\Entity\Empresa $empresa,
        User $user,
        ?Funcionario $funcionario,
    ): void {
        if ($user->hasPlatformAccess()) {
            throw new RhProcessException('Contas globais da plataforma não podem ser vinculadas por admissão.');
        }

        $userEmpresa = $user->getEmpresa();
        if ($userEmpresa !== null && $empresa !== null && $userEmpresa->getId() !== $empresa->getId()) {
            throw new RhProcessException('Esta conta já pertence a outra empresa.');
        }

        if ($empresa !== null) {
            $linkedFunc = $this->funcionarioRepo->findOneByUser($empresa, $user);
            if ($linkedFunc !== null && ($funcionario === null || $linkedFunc->getId() !== $funcionario->getId())) {
                throw new RhProcessException('Esta conta já está vinculada a outro colaborador desta empresa.');
            }
        }
    }

    private function applyUserLink(User $user, ?\App\Entity\Empresa $empresa, string $perfil): void
    {
        $perfil = $this->normalizeAssignablePerfil($perfil);

        if ($user->getEmpresa() === null && $empresa !== null) {
            $user->setEmpresa($empresa);
        }

        $user->setPerfil($perfil);
        $user->setRoles([$this->rolePorPerfil($perfil)]);

        if (!$user->isAtivo()) {
            $user->setAtivo(true);
        }
    }

    private function isPlatformActuallyLinked(RhOnboardingProcess $process): bool
    {
        $user = $process->getFuncionario()?->getUser();
        if ($user === null) {
            return false;
        }

        if ($this->normalizeEmail($user->getEmail()) !== $this->normalizeEmail($process->getEmail())) {
            return false;
        }

        return $user->getEmpresa() !== null;
    }

    private function linkUserToProcessFuncionario(RhOnboardingProcess $process, User $user): void
    {
        $funcionario = $process->getFuncionario();
        if (!$funcionario) {
            return;
        }

        $existingUser = $funcionario->getUser();
        if ($existingUser !== null && $existingUser->getId() !== $user->getId()) {
            throw new RhProcessException('Este funcionário já está vinculado a outra conta.');
        }

        $funcionario->setUser($user);
    }

    private function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    private function normalizeAssignablePerfil(string $perfil): string
    {
        $perfil = strtoupper(trim($perfil));

        if (!\in_array($perfil, self::ASSIGNABLE_PERFIS, true)) {
            return 'MEMBRO';
        }

        return $perfil;
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
