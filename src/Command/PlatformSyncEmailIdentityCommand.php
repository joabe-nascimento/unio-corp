<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\PlatformConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:platform:sync-email-identity',
    description: 'Sincroniza e-mails institucionais (contato + conta PLATFORM_OWNER) após deploy',
)]
final class PlatformSyncEmailIdentityCommand extends Command
{
    private const LEGACY_OWNER_EMAIL = 'joabenascimento1@outlook.com';

    private const OWNER_EMAIL = 'joabe@uniowork.com.br';

    private const SUPPORT_EMAIL = 'unio@uniowork.com.br';

    private const WEBSITE = 'https://uniowork.com.br';

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private PlatformConfigService $platformConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force-config',
            null,
            InputOption::VALUE_NONE,
            'Sobrescreve suporte_email e website mesmo se já preenchidos',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $forceConfig = (bool) $input->getOption('force-config');

        $owner = $this->userRepo->findOneBy(['perfil' => 'PLATFORM_OWNER']);
        if ($owner === null) {
            $owner = $this->userRepo->findOneBy(['email' => self::LEGACY_OWNER_EMAIL]);
        }

        if ($owner !== null) {
            $current = mb_strtolower(trim((string) $owner->getEmail()));
            if ($current === mb_strtolower(self::LEGACY_OWNER_EMAIL)) {
                $owner->setEmail(self::OWNER_EMAIL);
                $this->em->flush();
                $io->writeln('<info>Conta PLATFORM_OWNER migrada para ' . self::OWNER_EMAIL . '</info>');
            } elseif ($current === mb_strtolower(self::OWNER_EMAIL)) {
                $io->writeln('<comment>Conta PLATFORM_OWNER já usa ' . self::OWNER_EMAIL . '</comment>');
            } else {
                $io->writeln('<comment>PLATFORM_OWNER com e-mail ' . $current . ' — não alterado</comment>');
            }
        } else {
            $io->writeln('<comment>Nenhuma conta PLATFORM_OWNER encontrada</comment>');
        }

        $patch = [];
        $currentSupport = trim((string) $this->platformConfig->get('suporte_email'));
        $currentWebsite = trim((string) $this->platformConfig->get('website'));

        if ($forceConfig || $currentSupport === '' || $currentSupport === self::LEGACY_OWNER_EMAIL) {
            $patch['suporte_email'] = self::SUPPORT_EMAIL;
        }

        if ($forceConfig || $currentWebsite === '') {
            $patch['website'] = self::WEBSITE;
        }

        if ($patch !== []) {
            $this->platformConfig->save(array_merge($this->platformConfig->all(), $patch));
            $io->writeln('<info>Configuração da plataforma atualizada: ' . implode(', ', array_keys($patch)) . '</info>');
        } else {
            $io->writeln('<comment>Configuração de contato já preenchida — use --force-config para sobrescrever</comment>');
        }

        return Command::SUCCESS;
    }
}
