<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Interface administrativa para importação manual de dados de tokens.
 *
 * Permite importar dados históricos visualizados no portal Azure
 * sem necessidade de Azure CLI ou credenciais programáticas.
 */
#[Route('/admin/ai-tokens', name: 'admin_ai_tokens_')]
class AiTokensAdminController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    #[Route('/import', name: 'import')]
    public function import(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Apenas joabe.nascimento@unio.dev pode importar
        if (mb_strtolower($user->getEmail() ?? '') !== 'joabe.nascimento@unio.dev') {
            throw $this->createAccessDeniedException();
        }

        $imported = false;
        $error = null;

        if ($request->isMethod('POST')) {
            try {
                $todayTokens = (int) ($request->request->get('today_tokens') ?? 0);
                $todayRequests = (int) ($request->request->get('today_requests') ?? 0);
                $monthTokens = (int) ($request->request->get('month_tokens') ?? 0);
                $monthRequests = (int) ($request->request->get('month_requests') ?? 0);
                $lifetimeTokens = (int) ($request->request->get('lifetime_tokens') ?? 0);
                $lifetimeRequests = (int) ($request->request->get('lifetime_requests') ?? 0);

                $this->importToJurisFlow([
                    'today' => [
                        'total_tokens' => $todayTokens,
                        'prompt_tokens' => (int) ($todayTokens * 0.5),
                        'completion_tokens' => (int) ($todayTokens * 0.5),
                        'requests' => $todayRequests,
                    ],
                    'month' => [
                        'total_tokens' => $monthTokens,
                        'prompt_tokens' => (int) ($monthTokens * 0.5),
                        'completion_tokens' => (int) ($monthTokens * 0.5),
                        'requests' => $monthRequests,
                    ],
                    'lifetime' => [
                        'total_tokens' => $lifetimeTokens,
                        'prompt_tokens' => (int) ($lifetimeTokens * 0.5),
                        'completion_tokens' => (int) ($lifetimeTokens * 0.5),
                        'requests' => $lifetimeRequests,
                    ],
                ]);

                $imported = true;
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('admin/ai_tokens_import.html.twig', [
            'imported' => $imported,
            'error' => $error,
        ]);
    }

    /**
     * @param array{today: array, month: array, lifetime: array} $data
     */
    private function importToJurisFlow(array $data): void
    {
        $jurisflowUrl = $_ENV['JURISFLOW_AI_BASE_URL'] ?? 'http://localhost:8090';

        $response = $this->httpClient->request('POST', $jurisflowUrl . '/v1/usage/import', [
            'json' => $data,
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Falha ao importar para JurisFlow AI');
        }
    }
}
