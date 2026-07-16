<?php

namespace App\Service\PosOperatorio;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Localidades BR via APIs públicas (IBGE + ViaCEP + BrasilAPI + OpenCEP).
 *
 * Usa cliente HTTP próprio com verificação SSL relaxada: em vários hosts
 * compartilhados (ex.: HostGator / PHP WinGet) o cacert.pem vem quebrado e
 * bloqueia 100% das consultas HTTPS.
 */
final class ClinicLocalidadeService
{
    private HttpClientInterface $httpClient;

    public function __construct()
    {
        // Cliente dedicado: o HttpClient padrão do Symfony herda cacert.pem
        // inválido em vários ambientes e falha em 100% das chamadas HTTPS.
        $this->httpClient = HttpClient::create([
            'timeout' => 15,
            'verify_peer' => false,
            'verify_host' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'UnioSaude-Localidades/1.0',
            ],
        ]);
    }

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

        // ViaCEP primeiro: costuma trazer código IBGE do município.
        return $this->lookupCepViaCep($digits)
            ?? $this->lookupCepBrasilApi($digits)
            ?? $this->lookupCepOpenCep($digits);
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

        $fromIbge = $this->listCidadesIbge($uf);
        if ($fromIbge !== []) {
            return $fromIbge;
        }

        return $this->listCidadesBrasilApi($uf);
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
                        if (!\is_array($row)) {
                            continue;
                        }
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
                ['timeout' => 15]
            );
            if ($response->getStatusCode() >= 400) {
                return [];
            }
            $rows = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return [];
        }

        if (!\is_array($rows) || $rows === []) {
            return [];
        }

        // Resposta de erro da API às vezes vem como objeto associativo.
        if (!array_is_list($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $nome = trim((string) ($row['nome'] ?? ''));
            $ibge = trim((string) ($row['id'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $out[] = ['nome' => $nome, 'ibge' => $ibge];
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

        return $out;
    }

    /**
     * @return list<array{nome: string, ibge: string}>
     */
    private function listCidadesBrasilApi(string $uf): array
    {
        try {
            // Sem query "providers": em alguns ambientes ela devolve lista vazia/erro.
            $response = $this->httpClient->request(
                'GET',
                'https://brasilapi.com.br/api/ibge/municipios/v1/'.$uf,
                ['timeout' => 15]
            );
            if ($response->getStatusCode() >= 400) {
                return [];
            }
            $rows = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return [];
        }

        if (!\is_array($rows) || $rows === [] || !array_is_list($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $nome = trim((string) ($row['nome'] ?? ''));
            $ibge = trim((string) ($row['codigo_ibge'] ?? $row['codigo'] ?? ''));
            if ($nome === '') {
                continue;
            }
            // BrasilAPI costuma vir em CAIXA ALTA — normaliza título básico.
            $nome = mb_convert_case(mb_strtolower($nome, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            $out[] = ['nome' => $nome, 'ibge' => $ibge];
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

        if (!\is_array($data) || !empty($data['erro']) || empty($data['city'])) {
            return null;
        }

        $ibge = $data['city_ibge'] ?? $data['ibge'] ?? null;

        return [
            'cep' => $this->formatCep($digits),
            'logradouro' => trim((string) ($data['street'] ?? '')),
            'complemento' => '',
            'bairro' => trim((string) ($data['neighborhood'] ?? '')),
            'cidade' => trim((string) ($data['city'] ?? '')),
            'uf' => strtoupper(trim((string) ($data['state'] ?? ''))),
            'ibge' => $ibge !== null && $ibge !== '' ? (string) $ibge : null,
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

        if (!\is_array($data) || !empty($data['erro']) || empty($data['localidade'])) {
            return null;
        }

        return [
            'cep' => $this->formatCep($digits),
            'logradouro' => trim((string) ($data['logradouro'] ?? '')),
            'complemento' => trim((string) ($data['complemento'] ?? '')),
            'bairro' => trim((string) ($data['bairro'] ?? '')),
            'cidade' => trim((string) ($data['localidade'] ?? '')),
            'uf' => strtoupper(trim((string) ($data['uf'] ?? ''))),
            'ibge' => isset($data['ibge']) && $data['ibge'] !== '' ? (string) $data['ibge'] : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function lookupCepOpenCep(string $digits): ?array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://opencep.com/v1/'.$digits,
                ['timeout' => 10]
            );
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface|\Throwable) {
            return null;
        }

        if (!\is_array($data) || !empty($data['erro']) || empty($data['localidade'])) {
            return null;
        }

        return [
            'cep' => $this->formatCep($digits),
            'logradouro' => trim((string) ($data['logradouro'] ?? '')),
            'complemento' => trim((string) ($data['complemento'] ?? '')),
            'bairro' => trim((string) ($data['bairro'] ?? '')),
            'cidade' => trim((string) ($data['localidade'] ?? '')),
            'uf' => strtoupper(trim((string) ($data['uf'] ?? ''))),
            'ibge' => isset($data['ibge']) && $data['ibge'] !== '' ? (string) $data['ibge'] : null,
        ];
    }

    private function formatCep(string $digits): string
    {
        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }
}
