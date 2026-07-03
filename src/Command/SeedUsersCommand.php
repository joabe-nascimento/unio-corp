<?php

namespace App\Command;

use App\Command\Concern\ProdSeedGuardTrait;
use App\Entity\Empresa;
use App\Entity\User;
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
    public const SEED_PASSWORD = 'unio123';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
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

        $empresaRepo = $this->em->getRepository(Empresa::class);
        $userRepo    = $this->em->getRepository(User::class);

        $empresas = [];
        foreach ([
            ['nome' => self::DEMO_EMPRESA_NOME, 'cnpj' => '11.111.111/0001-11', 'setor' => 'Tecnologia', 'logo' => 'images/logos/unio-demo.svg'],
            ['nome' => 'Nexus Saúde S/A',        'cnpj' => '22.222.222/0001-22', 'setor' => 'Saúde',      'logo' => 'images/logos/nexus-saude.svg'],
            ['nome' => 'Edu360 Ensino',           'cnpj' => '33.333.333/0001-33', 'setor' => 'Educação',   'logo' => 'images/logos/edu360.svg'],
        ] as $data) {
            $emp = $empresaRepo->findOneBy(['cnpj' => $data['cnpj']]) ?? new Empresa();
            $emp->setNome($data['nome'])->setCnpj($data['cnpj'])->setSetor($data['setor'])->setLogo($data['logo']);
            $this->em->persist($emp);
            $empresas[] = $emp;
        }
        $this->em->flush();
        $io->text('Empresas: 3 sincronizadas (IDs preservados).');

        $seedUsers = [
            ['nome' => 'Tenant Master',    'email' => 'tenant@unio.dev',     'perfil' => 'TENANT',            'empresa' => null],
            ['nome' => 'Gestor Oliveira',   'email' => 'gestor@unio.dev',     'perfil' => 'GESTOR',            'empresa' => $empresas[0]],
            ['nome' => 'Gestor Costa',      'email' => 'gestor.eq@unio.dev',  'perfil' => 'GESTOR_EQUIPE',     'empresa' => $empresas[0]],
            ['nome' => 'Supervisor Geral',  'email' => 'supervisor@unio.dev', 'perfil' => 'SUPERVISOR',        'empresa' => $empresas[0]],
            ['nome' => 'Supervisor Equipe', 'email' => 'sup.eq@unio.dev',     'perfil' => 'SUPERVISOR_EQUIPE', 'empresa' => $empresas[0]],
            ['nome' => 'Membro Santos',     'email' => 'membro@unio.dev',     'perfil' => 'MEMBRO',            'empresa' => $empresas[0]],
            ['nome' => 'Gestor Nexus',      'email' => 'gestor@nexus.dev',    'perfil' => 'GESTOR',            'empresa' => $empresas[1]],
            ['nome' => 'Gestor Edu360',     'email' => 'gestor@edu360.dev',   'perfil' => 'GESTOR',            'empresa' => $empresas[2]],
        ];

        foreach ($seedUsers as $data) {
            $user = $userRepo->findOneBy(['email' => $data['email']]) ?? new User();
            $isNew = $user->getId() === null;
            $user->setNome($data['nome'])->setEmail($data['email'])->setPerfil($data['perfil']);
            $user->setRoles([$user->getRolePrincipal()]);
            if ($isNew) {
                $user->setPassword($this->hasher->hashPassword($user, self::SEED_PASSWORD));
            }
            $user->setAtivo(true);
            if ($data['empresa']) {
                $user->setEmpresa($data['empresa']);
            }
            $this->em->persist($user);
            $io->text('  ' . $data['email'] . ' [' . $data['perfil'] . ']');
        }

        $this->em->flush();
        $io->success('Seed concluído. Senha: ' . self::SEED_PASSWORD);

        return Command::SUCCESS;
    }
}
