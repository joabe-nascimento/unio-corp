<?php

namespace App\Command;

use App\Entity\PlatformAuditLog;
use App\Service\Platform\PlatformAuditService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:platform-audit:record-deploy',
    description: 'Registra evento de deploy (sucesso ou falha) a partir do relatório em var/log/deploy-report.txt',
)]
final class PlatformAuditDeployCommand extends Command
{
    public function __construct(
        private KernelInterface $kernel,
        private PlatformAuditService $audit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('report', null, InputOption::VALUE_OPTIONAL, 'Caminho do relatório de deploy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reportPath = (string) ($input->getOption('report')
            ?: $this->kernel->getProjectDir() . '/var/log/deploy-report.txt');

        if (!is_readable($reportPath)) {
            $output->writeln('<comment>Relatório de deploy não encontrado — nada registrado.</comment>');

            return Command::SUCCESS;
        }

        $content = (string) file_get_contents($reportPath);
        $success = str_contains($content, 'RELATÓRIO DE SUCESSO');
        $failure = str_contains($content, 'RELATÓRIO DE FALHA');

        $revisionFile = $this->kernel->getProjectDir() . '/var/deploy/revision.json';
        $commit = null;
        if (is_readable($revisionFile)) {
            try {
                /** @var array<string, mixed> $revision */
                $revision = json_decode((string) file_get_contents($revisionFile), true, 512, JSON_THROW_ON_ERROR);
                $commit = isset($revision['commit']) ? (string) $revision['commit'] : null;
            } catch (\JsonException) {
                $commit = null;
            }
        }

        $resultado = $success
            ? PlatformAuditLog::OUTCOME_SUCCESS
            : ($failure ? PlatformAuditLog::OUTCOME_FAILURE : PlatformAuditLog::OUTCOME_WARNING);

        $mensagem = $success
            ? 'Deploy concluído com sucesso'
            : ($failure ? 'Deploy falhou — veja relatório' : 'Deploy registrado (status desconhecido)');

        if ($commit !== null && $commit !== '' && $commit !== 'unknown') {
            $mensagem .= ' · commit ' . substr($commit, 0, 12);
        }

        $this->audit->record(
            PlatformAuditLog::CATEGORY_DEPLOY,
            PlatformAuditLog::ACTION_DEPLOY,
            $resultado,
            $mensagem,
            null,
            null,
            'deploy',
            null,
            $commit,
            null,
            ['report_path' => $reportPath, 'report_excerpt' => mb_substr($content, 0, 500)],
        );

        $output->writeln('<info>Evento de deploy registrado (' . $resultado . ').</info>');

        return Command::SUCCESS;
    }
}
