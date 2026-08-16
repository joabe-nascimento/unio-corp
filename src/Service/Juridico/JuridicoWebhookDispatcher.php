<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoWebhookEntrega;
use App\Entity\JuridicoWebhookSubscription;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoWebhookSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JuridicoWebhookDispatcher
{
    public const EVENTOS = [
        'processo.atualizado',
        'prazo.criado',
        'prazo.vencendo',
        'publicacao.nova',
        'documento.indexado',
        'audiencia.agendada',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoWebhookSubscriptionRepository $repo,
        private HttpClientInterface $httpClient,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(Empresa $empresa, string $evento, array $payload): void
    {
        foreach ($this->repo->findAtivasForEvento($empresa, $evento) as $sub) {
            $this->enviar($sub, $evento, $payload);
        }
    }

    /** @param array<string, mixed> $data */
    public function criar(Empresa $empresa, array $data): JuridicoWebhookSubscription
    {
        $url = trim((string) ($data['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new JuridicoProcessException('Informe uma URL HTTPS válida para o webhook.');
        }

        $eventos = $data['eventos'] ?? ['prazo.vencendo', 'publicacao.nova'];
        if (\is_string($eventos)) {
            $eventos = array_values(array_filter(array_map('trim', explode(',', $eventos))));
        }

        $sub = (new JuridicoWebhookSubscription())
            ->setEmpresa($empresa)
            ->setUrl($url)
            ->setEventos($eventos !== [] ? $eventos : ['*']);

        $this->em->persist($sub);
        $this->em->flush();

        return $sub;
    }

    public function revogar(JuridicoWebhookSubscription $sub): void
    {
        $sub->setAtivo(false);
        $this->em->flush();
    }

    /** @param array<string, mixed> $payload */
    private function enviar(JuridicoWebhookSubscription $sub, string $evento, array $payload): void
    {
        $body = json_encode([
            'evento' => $evento,
            'escritorio' => $sub->getEmpresa()->getNome(),
            'enviado_em' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'data' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $assinatura = hash_hmac('sha256', $body, $sub->getSecret());

        $entrega = (new JuridicoWebhookEntrega())
            ->setSubscription($sub)
            ->setEvento($evento)
            ->setPayload($payload);

        try {
            $response = $this->httpClient->request('POST', $sub->getUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Unio-Signature' => $assinatura,
                    'X-Unio-Event' => $evento,
                ],
                'body' => $body,
                'timeout' => 8,
            ]);
            $code = $response->getStatusCode();
            $entrega->setStatusHttp($code)->setSucesso($code >= 200 && $code < 300);
            $entrega->setResposta(mb_substr($response->getContent(false), 0, 500));
            $sub->setFalhasConsecutivas($entrega->isSucesso() ? 0 : $sub->getFalhasConsecutivas() + 1);
        } catch (\Throwable $e) {
            $entrega->setSucesso(false)->setResposta($e->getMessage());
            $sub->setFalhasConsecutivas($sub->getFalhasConsecutivas() + 1);
        }

        $sub->setUltimoEnvioEm(new \DateTimeImmutable());
        if ($sub->getFalhasConsecutivas() >= 8) {
            $sub->setAtivo(false);
        }
        $this->em->persist($entrega);
        $this->em->flush();
    }
}
