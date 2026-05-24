<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserProductGrant;
use App\Repository\UserRepository;
use App\Service\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-product-grants',
    description: 'Popula user_product_grant a partir do template DEFAULT_GRANTS (usuários seed huplex.dev)',
)]
class SeedProductGrantsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Recria grants existentes dos usuários seed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $created = 0;
        $skipped = 0;

        foreach (PermissionService::DEFAULT_GRANTS as $scope => $members) {
            foreach ($members as $memberId => $products) {
                $user = $this->findUserByMemberId($memberId);
                if (!$user) {
                    $io->warning("Usuário não encontrado para member id: {$memberId}");
                    continue;
                }

                foreach ($products as $productId => $perfilGrant) {
                    $existing = $this->em->getRepository(UserProductGrant::class)->findOneBy([
                        'user' => $user,
                        'scope' => $scope,
                        'productId' => $productId,
                    ]);

                    if ($existing && !$force) {
                        ++$skipped;
                        continue;
                    }

                    if ($existing) {
                        $existing->setPerfilGrant($perfilGrant);
                    } else {
                        $grant = (new UserProductGrant())
                            ->setUser($user)
                            ->setScope($scope)
                            ->setProductId($productId)
                            ->setPerfilGrant($perfilGrant);
                        $this->em->persist($grant);
                    }
                    ++$created;
                }
            }
        }

        $this->em->flush();
        $io->success("Grants: {$created} gravado(s), {$skipped} ignorado(s). Use --force para sobrescrever.");

        return Command::SUCCESS;
    }

    private function findUserByMemberId(string $memberId): ?User
    {
        foreach ($this->userRepo->findAll() as $user) {
            if (PermissionService::memberIdFromEmail((string) $user->getEmail()) === $memberId) {
                return $user;
            }
        }

        return null;
    }
}
