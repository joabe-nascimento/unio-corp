<?php

namespace App\Service\PosOperatorio;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Localidades BR via APIs públicas (BrasilAPI + IBGE + ViaCEP fallback).
 */
final class ClinicLocalidadeService
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * @return list<array{value: string, label: string}>
     */
    public function listUfs(): array
    {
        return ClinicCadastroRules::ufSelectOptions(false);
    }

    /**
     * @return array{
     *     cep: string,
     *     logradouro: string,
     *     complemento: string,
     *     bairro: string,
     *     cidade: string,
     *     uf: string,
     *     ibge: string|null
     * }|null
     */
    public function lookupCep(string $cep): ?array
    {
        $digits = ClinicCadastroRules::digitsOnly($cep);
        if (strlen($digits) !== 8) {
            throw new \InvalidArgumentException('CEP deve ter 8 dígitos.');
        }

        $fromBrasil = $this->lookupCepBrasilApi($digits);
        if ($fromBrasil !== null) {
            return $fromBrasil;
        }

        return $this->lookupCepViaCep($digits);
    }

    /**
     * @return list<array{nome: string, ibge: string}>
     */
    public function listCidades(string $uf): array
    {
        $uf = strtoupper(trim($uf));
        if (!\in_array($uf, ClinicCadastroRules::UFS_BRASIL, true)) {
            throw new \InvalidArgumentException('UF inválida.');
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://brasilapi.com.br/api/ibge/municipios/v1/'.$uf,
                [
                    'timeout' => 12,
                    'query' => ['providers' => 'dados-abertos-br,gov,wikipedia'],
                ]
            );
            if ($response->getStatusCode() >= 400) {
                return $this->listCidadesIbge($uf);
            }
            /** @var list<array<string, mixed>> $rows */
            $rows = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return $this->listCidadesIbge($uf);
        }

        $out = [];
        foreach ($rows as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));
            $ibge = trim((string) ($row['codigo_ibge'] ?? $row['codigo'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $out[] = ['nome' => $nome, 'ibge' => $ibge];
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        return $out;
    }

    /**
     * Distritos IBGE (+ bairro opcional do CEP) — melhor proxy gratuito de “bairros”.
     *
     * @return list<string>
     */
    public function listBairros(?string $ibge, ?string $extraBairro = null): array
    {
        $names = [];
        $ibgeCode = trim((string) $ibge);
        if ($ibgeCode !== '' && ctype_digit($ibgeCode)) {
            try {
                $response = $this->httpClient->request(
                    'GET',
                    'https://servicodados.ibge.gov.br/api/v1/localidades/municipios/'.$ibgeCode.'/distritos',
                    ['timeout' => 12]
                );
                if ($response->getStatusCode() < 400) {
                    /** @var list<array<string, mixed>> $rows */
                    $rows = $response->toArray(false);
                    foreach ($rows as $row) {
                        $nome = trim((string) ($row['nome'] ?? ''));
                        if ($nome !== '') {
                            $names[$nome] = true;
                        }
                    }
                }
            } catch (TransportExceptionInterface|\Throwable) {
                // ignora — ainda podemos devolver o bairro do CEP
            }
        }

        $extra = trim((string) $extraBairro);
        if ($extra !== '') {
            $names[$extra] = true;
        }

        $list = array_keys($names);
        natcasesort($list);

        return array_values($list);
    }

    /**
     * @return list<array{nome: string, ibge: string}>
     */
    private function listCidadesIbge(string $uf): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://servicodados.ibge.gov.br/api/v1/localidades/estados/'.$uf.'/municipios',
                ['timeout' => 12]
            );
            if ($response->getStatusCode() >= 400) {
                return [];
            }
            /** @var list<array<string, mixed>> $rows */
            $rows = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));
            $ibge = trim((string) ($row['id'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $out[] = ['nome' => $nome, 'ibge' => (string) $ibge];
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function lookupCepBrasilApi(string $digits): ?array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://brasilapi.com.br/api/cep/v2/'.$digits,
                ['timeout' => 10]
            );
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return null;
        }

        if (!empty($data['erro']) || empty($data['city'])) {
            return null;
        }

        return [
            'cep' => $this->formatCep($digits),
            'logradouro' => trim((string) ($data['street'] ?? '')),
            'complemento' => '',
            'bairro' => trim((string) ($data['neighborhood'] ?? '')),
            'cidade' => trim((string) ($data['city'] ?? '')),
            'uf' => strtoupper(trim((string) ($data['state'] ?? ''))),
            'ibge' => isset($data['city_ibge']) ? (string) $data['city_ibge'] : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function lookupCepViaCep(string $digits): ?array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://viacep.com.br/ws/'.$digits.'/json/',
                ['timeout' => 10]
            );
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return null;
        }

        if (!empty($data['erro'])) {
            return null;
        }

        return [
            'cep' => $this->formatCep($digits),
            'logradouro' => trim((string) ($data['logradouro'] ?? '')),
            'complemento' => trim((string) ($data['complemento'] ?? '')),
            'bairro' => trim((string) ($data['bairro'] ?? '')),
            'cidade' => trim((string) ($data['localidade'] ?? '')),
            'uf' => strtoupper(trim((string) ($data['uf'] ?? ''))),
            'ibge' => isset($data['ibge']) ? (string) $data['ibge'] : null,
        ];
    }

    private function formatCep(string $digits): string
    {
        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }
}
