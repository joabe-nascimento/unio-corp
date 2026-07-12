<?php

namespace App\Service\Wallet;

use App\Wallet\WalletPassPayload;

/**
 * Gera arquivo .pkpass assinado para Apple Wallet.
 */
final class AppleWalletPassBuilder
{
    public function __construct(
        private ClinicWalletConfig $config,
        private WalletPassAssetProvider $assets,
    ) {}

    public function build(WalletPassPayload $payload): string
    {
        if (!$this->config->isAppleReady()) {
            throw new \RuntimeException('Apple Wallet não configurado no servidor.');
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Extensão ZIP necessária para gerar .pkpass.');
        }

        $passJson = json_encode($this->passDefinition($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $files = ['pass.json' => $passJson] + $this->assets->appleAssets();

        $manifest = [];
        foreach ($files as $name => $content) {
            $manifest[$name] = sha1($content);
        }
        $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $signature = $this->signManifest($manifestJson);

        $zipPath = tempnam(sys_get_temp_dir(), 'pkpass_');
        if ($zipPath === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário.');
        }

        $archive = new \ZipArchive();
        if ($archive->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new \RuntimeException('Não foi possível montar o .pkpass.');
        }

        $archive->addFromString('pass.json', $passJson);
        foreach ($this->assets->appleAssets() as $name => $content) {
            $archive->addFromString($name, $content);
        }
        $archive->addFromString('manifest.json', $manifestJson);
        $archive->addFromString('signature', $signature);
        $archive->close();

        $binary = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return $binary;
    }

    /** @return array<string, mixed> */
    private function passDefinition(WalletPassPayload $payload): array
    {
        $expiration = $this->expirationIso($payload->validUntil);

        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $this->config->applePassTypeId(),
            'serialNumber' => $payload->serialNumber,
            'teamIdentifier' => $this->config->appleTeamId(),
            'organizationName' => $payload->organizationName,
            'description' => $payload->type->label(),
            'logoText' => 'Unio Saúde',
            'foregroundColor' => 'rgb(255, 255, 255)',
            'backgroundColor' => 'rgb(15, 118, 110)',
            'labelColor' => 'rgb(226, 232, 240)',
            'expirationDate' => $expiration,
            'generic' => [
                'primaryFields' => [[
                    'key' => 'patient',
                    'label' => 'PACIENTE',
                    'value' => $payload->patientName,
                ]],
                'secondaryFields' => [
                    [
                        'key' => 'procedure',
                        'label' => 'PROCEDIMENTO',
                        'value' => $payload->procedureLabel,
                    ],
                    [
                        'key' => 'code',
                        'label' => 'CÓDIGO',
                        'value' => $payload->verificationCode,
                    ],
                ],
                'auxiliaryFields' => [
                    [
                        'key' => 'valid',
                        'label' => 'VÁLIDO ATÉ',
                        'value' => $payload->validUntil,
                    ],
                    [
                        'key' => 'doctor',
                        'label' => 'MÉDICO',
                        'value' => $payload->doctorName,
                    ],
                ],
                'backFields' => [
                    [
                        'key' => 'patient_code',
                        'label' => 'Código do paciente',
                        'value' => $payload->patientCode,
                    ],
                    [
                        'key' => 'surgery',
                        'label' => 'Cirurgia',
                        'value' => $payload->surgeryDate,
                    ],
                    [
                        'key' => 'issued',
                        'label' => 'Emitido em',
                        'value' => $payload->issuedAt,
                    ],
                    [
                        'key' => 'plan',
                        'label' => 'Plano',
                        'value' => $payload->planLabel,
                    ],
                    [
                        'key' => 'verify',
                        'label' => 'Validação pública',
                        'value' => $payload->verificationUrl,
                    ],
                ],
            ],
            'barcode' => [
                'format' => 'PKBarcodeFormatQR',
                'message' => $payload->verificationUrl,
                'messageEncoding' => 'iso-8859-1',
            ],
            'barcodes' => [[
                'format' => 'PKBarcodeFormatQR',
                'message' => $payload->verificationUrl,
                'messageEncoding' => 'iso-8859-1',
            ]],
        ];
    }

    private function signManifest(string $manifestJson): string
    {
        $manifestFile = tempnam(sys_get_temp_dir(), 'manifest_');
        $signatureFile = tempnam(sys_get_temp_dir(), 'sig_');
        if ($manifestFile === false || $signatureFile === false) {
            throw new \RuntimeException('Falha ao preparar assinatura Apple Wallet.');
        }

        file_put_contents($manifestFile, $manifestJson);

        $certs = $this->loadCertificate();
        $wwdr = $this->config->appleWwdrPath();
        if ($wwdr === null) {
            throw new \RuntimeException('Certificado WWDR não encontrado.');
        }

        $flags = PKCS7_BINARY | PKCS7_DETACHED;
        $ok = openssl_pkcs7_sign(
            $manifestFile,
            $signatureFile,
            $certs['cert'],
            $certs['key'],
            [],
            $flags,
            $wwdr,
        );

        @unlink($manifestFile);

        if (!$ok) {
            @unlink($signatureFile);
            throw new \RuntimeException('Falha ao assinar manifest do Apple Wallet.');
        }

        $signed = (string) file_get_contents($signatureFile);
        @unlink($signatureFile);

        return $this->extractSignatureDer($signed);
    }

    /** @return array{cert: \OpenSSLCertificate|string, key: \OpenSSLAsymmetricKey|resource|string} */
    private function loadCertificate(): array
    {
        $path = $this->config->appleCertPath();
        if ($path === null) {
            throw new \RuntimeException('Certificado Apple Wallet não configurado.');
        }

        $store = (string) file_get_contents($path);
        $certs = [];
        if (!openssl_pkcs12_read($store, $certs, $this->config->appleCertPassword())) {
            throw new \RuntimeException('Não foi possível ler o certificado .p12 do Apple Wallet.');
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new \RuntimeException('Certificado Apple Wallet inválido.');
        }

        return [
            'cert' => $certs['cert'],
            'key' => $certs['pkey'],
        ];
    }

    private function extractSignatureDer(string $signed): string
    {
        $begin = strpos($signed, "filename=\"smime.p7s\"");
        if ($begin === false) {
            throw new \RuntimeException('Assinatura PKCS7 inválida.');
        }

        $start = strpos($signed, "\n\n", $begin);
        if ($start === false) {
            throw new \RuntimeException('Assinatura PKCS7 inválida.');
        }
        $start += 2;

        $end = strpos($signed, "\n\n", $start);
        if ($end === false) {
            throw new \RuntimeException('Assinatura PKCS7 inválida.');
        }

        return substr($signed, $start, $end - $start);
    }

    private function expirationIso(string $validUntilBr): string
    {
        $date = \DateTimeImmutable::createFromFormat('d/m/Y', $validUntilBr);
        if ($date === false) {
            return (new \DateTimeImmutable('+30 days'))->setTime(23, 59, 59)->format('c');
        }

        return $date->setTime(23, 59, 59)->format('c');
    }
}
