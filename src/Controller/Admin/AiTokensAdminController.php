<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Dev\DevSeedEmails;
use App\Service\Juridico\AzureMonitorTokenImporter;
use App\Service\Juridico\AzureTokenUsageStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin/ai-tokens', name: 'admin_ai_tokens_')]
class AiTokensAdminController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AzureMonitorTokenImporter $azureImporter,
        private readonly AzureTokenUsageStore $azureStore,
        private readonly string $jurisflowBaseUrl,
    ) {
    }

    #[Route('/import', name: 'import')]
    public function import(Request $request): Response
    {
        $this->denyUnlessJoabe();

        $imported = false;
        $error = null;
        $syncResult = null;
        $azureConfigured = $this->azureImporter->isConfigured();
        $lastSync = $this->azureStore->load();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', 'manual');

            if ($action === 'sync_azure') {
                try {
                    $syncResult = $this->azureImporter->sync();
                    $imported = true;
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            } else {
                try {
                    $todayTokens = (int) ($request->request->get('today_tokens') ?? 0);
                    $todayRequests = (int) ($request->request->get('today_requests') ?? 0);
                    $monthTokens = (int) ($request->request->get('month_tokens') ?? 0);
                    $monthRequests = (int) ($request->request->get('month_requests') ?? 0);
                    $lifetimeTokens = (int) ($request->request->get('lifetime_tokens') ?? 0);
                    $lifetimeRequests = (int) ($request->request->get('lifetime_requests') ?? 0);

                    $summary = [
                        'today' => $this->bucket($todayTokens, $todayRequests),
                        'month' => $this->bucket($monthTokens, $monthRequests),
                        'lifetime' => $this->bucket($lifetimeTokens, $lifetimeRequests),
                    ];

                    $this->azureStore->save($summary);
                    $this->pushToJurisFlow($summary);
                    $imported = true;
                    $syncResult = ['synced_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)] + $summary;
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('admin/ai_tokens_import.html.twig', [
            'imported' => $imported,
            'error' => $error,
            'syncResult' => $syncResult,
            'azureConfigured' => $azureConfigured,
            'lastSync' => $lastSync,
        ]);
    }

    /** @return array{total_tokens: int, prompt_tokens: int, completion_tokens: int, requests: int} */
    private function bucket(int $tokens, int $requests): array
    {
        return [
            'total_tokens' => $tokens,
            'prompt_tokens' => (int) ($tokens * 0.5),
            'completion_tokens' => (int) ($tokens * 0.5),
            'requests' => $requests,
        ];
    }

    /**
     * @param array{today: array, month: array, lifetime: array} $data
     */
    private function pushToJurisFlow(array $data): void
    {
        if ($this->jurisflowBaseUrl === '') {
            return;
        }

        try {
            $this->httpClient->request('POST', rtrim($this->jurisflowBaseUrl, '/') . '/v1/usage/import', [
                'json' => $data,
                'timeout' => 10,
            ]);
        } catch (\Throwable) {
            // JurisFlow opcional — o store local já é a fonte principal
        }
    }

    private function denyUnlessJoabe(): void
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (mb_strtolower($user->getEmail() ?? '') !== mb_strtolower(DevSeedEmails::JOABE)) {
            throw $this->createAccessDeniedException();
        }
    }
}
