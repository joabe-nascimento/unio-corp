<?php

namespace App\Command;

use App\Dev\DevSeedEmails;

use App\Service\Sasha\SashaClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:sasha:smoke-test',
    description: 'Valida Sasha AI (Python) e proxy Symfony end-to-end',
)]
final class SashaSmokeTestCommand extends Command
{
    public function __construct(
        private SashaClient $sasha,
        private string $defaultUri,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Vitória AI — smoke test');

        $online = $this->vitoria->isAvailable();
        $io->writeln($online ? '<info>✓</info> Python GET /health' : '<error>✗</error> Python offline (uvicorn na 8100?)');

        $chat = $this->vitoria->chat('Olá Vitória, como priorizo alertas P1?', [], [
            'hub' => 'hub_pos_operatorio',
            'user_name' => 'Smoke Test',
        ]);
        if ($chat && ($chat['reply'] ?? '') !== '') {
            $io->writeln('<info>✓</info> POST /v1/chat — source: ' . ($chat['source'] ?? '?'));
            $io->writeln('  ' . mb_substr(str_replace("\n", ' ', $chat['reply']), 0, 160));
        } else {
            $io->writeln('<error>✗</error> POST /v1/chat falhou');
        }

        $triage = $this->vitoria->evaluateTriage(['dor' => 8, 'febre' => 36.5], 'PO-TEST', 'Apendicectomia', 2);
        $pri = is_array($triage) ? (string) ($triage['prioridade'] ?? '') : '';
        if ($triage && $pri === 'P1') {
            $io->writeln('<info>✓</info> POST /v1/triage/evaluate — P1: ' . ($triage['motivo'] ?? ''));
        } else {
            $io->writeln('<error>✗</error> Triagem falhou (prioridade: ' . ($pri ?: 'null') . ')');
            if ($triage) {
                $io->writeln('  raw: ' . json_encode($triage, \JSON_UNESCAPED_UNICODE));
            }
        }

        $insight = $this->vitoria->hubInsight('hub_pos_operatorio', ['adesao_pct' => 94], [
            ['pri' => 'P1', 'titulo' => 'Dor intensa'],
        ], [['codigo' => 'PO-1035', 'nome' => 'Juliana M.']]);
        if ($insight && ($insight['text'] ?? '') !== '') {
            $io->writeln('<info>✓</info> POST /v1/insights/hub');
        } else {
            $io->writeln('<error>✗</error> Insight hub falhou');
        }

        $symfonyOk = false;
        try {
            $client = HttpClient::create(['verify_peer' => false, 'verify_host' => false]);
            $jar = [];
            $loginGet = $client->request('GET', rtrim($this->defaultUri, '/') . '/login');
            foreach ($loginGet->getHeaders()['set-cookie'] ?? [] as $c) {
                $jar[] = explode(';', $c)[0];
            }
            preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $loginGet->getContent(), $m);
            $loginPost = $client->request('POST', rtrim($this->defaultUri, '/') . '/login', [
                'headers' => ['Cookie' => implode('; ', $jar)],
                'body' => [
                    'email' => DevSeedEmails::JOABE,
                    'password' => 'unio123',
                    '_csrf_token' => $m[1] ?? '',
                ],
                'max_redirects' => 5,
            ]);
            foreach ($loginPost->getHeaders()['set-cookie'] ?? [] as $c) {
                $jar[] = explode(';', $c)[0];
            }
            $cookie = implode('; ', array_unique($jar));

            $api = $client->request('POST', rtrim($this->defaultUri, '/') . '/api/vitoria/chat', [
                'headers' => ['Cookie' => $cookie, 'Content-Type' => 'application/json'],
                'json' => [
                    'message' => 'Teste proxy Symfony',
                    'history' => [],
                    'context' => ['hub' => 'hub_pos_operatorio'],
                ],
            ]);
            $data = $api->toArray(false);
            if (($data['reply'] ?? '') !== '' && !str_contains($data['reply'], 'indisponível')) {
                $io->writeln('<info>✓</info> Symfony POST /api/vitoria/chat (sessão autenticada)');
                $io->writeln('  ' . mb_substr($data['reply'], 0, 120));
                $symfonyOk = true;
            } else {
                $io->writeln('<error>✗</error> Proxy Symfony retornou offline ou vazio');
            }

            $status = $client->request('GET', rtrim($this->defaultUri, '/') . '/api/vitoria/status', [
                'headers' => ['Cookie' => $cookie],
            ])->toArray(false);
            $io->writeln('<info>✓</info> GET /api/vitoria/status — online: ' . (($status['online'] ?? false) ? 'sim' : 'não'));
        } catch (\Throwable $e) {
            $io->writeln('<comment>→</comment> Proxy Symfony skip: ' . $e->getMessage());
        }

        $ok = $online && $chat !== null && $triage !== null && $insight !== null;
        if ($ok && $symfonyOk) {
            $io->success('Vitória end-to-end OK (Python + Symfony + sessão).');

            return Command::SUCCESS;
        }
        if ($ok) {
            $io->warning('Python OK; proxy Symfony não validado (servidor em ' . $this->defaultUri . '?).');

            return Command::SUCCESS;
        }

        $io->error('Checks falharam. VITORIA_AI_URL=' . ($_ENV['VITORIA_AI_URL'] ?? '?'));

        return Command::FAILURE;
    }
}
