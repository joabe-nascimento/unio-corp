<?php

namespace App\Command;

use App\Clinic\ClinicStaffRole;
use App\Command\Concern\ProdSeedGuardTrait;
use App\Dev\DevSeedEmails;
use App\Entity\Empresa;
use App\Entity\User;
use App\Service\Security\DefaultUserPasswordProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-users', description: 'Cria/atualiza empresas e usuários de teste (idempotente — nunca apaga dados)')]
class SeedUsersCommand extends Command
{
    use ProdSeedGuardTrait;

    public const DEMO_EMPRESA_NOME = 'Unio Demo';
    /** @deprecated Use DefaultUserPasswordProvider / APP_DEFAULT_USER_PASSWORD */
    public const SEED_PASSWORD = DefaultUserPasswordProvider::FALLBACK;

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private DefaultUserPasswordProvider $defaultPasswordProvider,
        private string $appEnv = 'dev',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configureProdSeedGuard();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($code = $this->refuseInProductionUnlessAllowed($input, $io)) !== null) {
            return $code;
        }

        $seedPassword = $this->defaultPasswordProvider->get();

        $empresaRepo = $this->em->getRepository(Empresa::class);
        $userRepo = $this->em->getRepository(User::class);

        $empresas = [];
        foreach ([
            ['nome' => self::DEMO_EMPRESA_NOME, 'cnpj' => '11.111.111/0001-11', 'setor' => 'Tecnologia', 'logo' => 'images/logos/unio-demo.svg'],
            ['nome' => 'Nexus Saúde S/A', 'cnpj' => '22.222.222/0001-22', 'setor' => 'Saúde', 'logo' => 'images/logos/nexus-saude.svg'],
            ['nome' => 'Edu360 Ensino', 'cnpj' => '33.333.333/0001-33', 'setor' => 'Educação', 'logo' => 'images/logos/edu360.svg'],
        ] as $data) {
            $emp = $empresaRepo->findOneBy(['cnpj' => $data['cnpj']]) ?? new Empresa();
            $emp->setNome($data['nome'])->setCnpj($data['cnpj'])->setSetor($data['setor'])->setLogo($data['logo']);
            $this->em->persist($emp);
            $empresas[] = $emp;
        }
        $this->em->flush();
        $io->text('Empresas: 3 sincronizadas (IDs preservados).');

        foreach ($this->seedUsers($empresas) as $data) {
            $email = $data['email'];
            $user = $userRepo->findOneBy(['email' => $email]);
            if (!$user instanceof User) {
                $legacyEmail = DevSeedEmails::legacyEmailFor($email);
                if ($legacyEmail !== null) {
                    $user = $userRepo->findOneBy(['email' => $legacyEmail]);
                }
            }
            $user ??= new User();
            $isNew = $user->getId() === null;
            $user->setNome($data['nome'])->setEmail($data['email'])->setPerfil($data['perfil']);
            $user->setRoles([$user->getRolePrincipal()]);
            // Contas clínicas: sempre regrava senha demo para login previsível.
            if ($isNew || !empty($data['reset_password'])) {
                $user->setPassword($this->hasher->hashPassword($user, $seedPassword));
            }
            $user->setAtivo(true);
            if ($data['empresa']) {
                $user->setEmpresa($data['empresa']);
            }
            $this->em->persist($user);
            $io->text('  ' . $data['email'] . ' [' . $data['perfil'] . ']' . (!empty($data['reset_password']) || $isNew ? ' senha=' . $seedPassword : ''));
        }

        // Migra Marcela (legado GESTOR na Nexus) para Coordenação clínica, se ainda existir.
        $marcela = $userRepo->findOneBy(['email' => DevSeedEmails::MARCELA]);
        if ($marcela instanceof User && $marcela->getPerfil() === 'GESTOR') {
            $marcela->setPerfil(ClinicStaffRole::COORDENACAO);
            $marcela->setRoles([$marcela->getRolePrincipal()]);
            $marcela->setPassword($this->hasher->hashPassword($marcela, $seedPassword));
            $this->em->persist($marcela);
            $io->text('  ' . DevSeedEmails::MARCELA . ' [COORDENACAO] migrada de GESTOR');
        }

        // Desativa contas legadas Unio Work (GESTOR/SUPERVISOR/MEMBRO) — não usam mais na clínica.
        $legacyEmails = [
            DevSeedEmails::RENATA,
            DevSeedEmails::RICARDO,
            DevSeedEmails::ANA_PAULA,
            DevSeedEmails::FELIPE,
            DevSeedEmails::LUCAS,
            DevSeedEmails::PATRICIA,
        ];
        foreach ($legacyEmails as $legacyEmail) {
            $legacyUser = $userRepo->findOneBy(['email' => $legacyEmail]);
            if (!$legacyUser instanceof User) {
                continue;
            }
            if (ClinicStaffRole::isClinicStaffPerfil($legacyUser->getPerfil()) || $legacyUser->hasPlatformAccess()) {
                continue;
            }
            if ($legacyUser->isAtivo()) {
                $legacyUser->setAtivo(false);
                $this->em->persist($legacyUser);
                $io->text('  ' . $legacyEmail . ' desativado (perfil legado ' . $legacyUser->getPerfil() . ')');
            }
        }

        $this->em->flush();
        $io->success('Seed concluído. Senha clínica/demo: ' . $seedPassword);

        return Command::SUCCESS;
    }

    /**
     * @param list<Empresa> $empresas
     *
     * @return list<array{nome: string, email: string, perfil: string, empresa: Empresa|null, reset_password?: bool}>
     */
    private function seedUsers(array $empresas): array
    {
        return [
            ['nome' => 'Joabe Nascimento', 'email' => DevSeedEmails::JOABE, 'perfil' => 'TENANT', 'empresa' => null],
            // Unio Saúde — apenas os 4 perfis clínicos (Nexus Saúde)
            [
                'nome' => 'Camila Souza',
                'email' => DevSeedEmails::CAMILA_RECEPCAO,
                'perfil' => ClinicStaffRole::RECEPCAO,
                'empresa' => $empresas[1],
                'reset_password' => true,
            ],
            [
                'nome' => 'Beatriz Nunes',
                'email' => DevSeedEmails::BEATRIZ_ENFERMAGEM,
                'perfil' => ClinicStaffRole::ENFERMAGEM,
                'empresa' => $empresas[1],
                'reset_password' => true,
            ],
            [
                'nome' => 'André Melo',
                'email' => DevSeedEmails::ANDRE_MEDICO,
                'perfil' => ClinicStaffRole::MEDICO,
                'empresa' => $empresas[1],
                'reset_password' => true,
            ],
            [
                'nome' => 'Helena Castro',
                'email' => DevSeedEmails::HELENA_COORDENACAO,
                'perfil' => ClinicStaffRole::COORDENACAO,
                'empresa' => $empresas[1],
                'reset_password' => true,
            ],
        ];
    }
}
