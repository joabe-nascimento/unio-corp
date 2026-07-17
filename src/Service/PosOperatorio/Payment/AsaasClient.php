<?php

namespace App\Service\PosOperatorio\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AsaasClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createCustomer(string $apiKey, string $env, array $payload): array
    {
        return $this->request('POST', $env, $apiKey, '/customers', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function createPayment(string $apiKey, string $env, array $payload): array
    {
        return $this->request('POST', $env, $apiKey, '/payments', $payload);
    }

    /** @return array<string, mixed> */
    public function getPayment(string $apiKey, string $env, string $paymentId): array
    {
        return $this->request('GET', $env, $apiKey, '/payments/'.rawurlencode($paymentId));
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $env, string $apiKey, string $path, ?array $json = null): array
    {
        $base = $env === 'production'
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';

        $options = [
            'headers' => [
                'access_token' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
        if ($json !== null) {
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, $base.$path, $options);
        $status = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($status >= 400) {
            $msg = (string) ($data['errors'][0]['description'] ?? $data['message'] ?? 'Erro Asaas HTTP '.$status);
            throw new \RuntimeException($msg);
        }

        return $data;
    }
}
