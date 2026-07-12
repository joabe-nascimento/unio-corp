<?php

namespace App\Service\Wallet;

/**
 * Credenciais e toggles para Apple Wallet / Google Wallet.
 */
final class ClinicWalletConfig
{
    public function __construct(
        private string $projectDir,
        private string $applePassTypeId,
        private string $appleTeamId,
        private string $appleCertPath,
        private string $appleCertPassword,
        private string $appleWwdrPath,
        private string $googleIssuerId,
        private string $googleServiceAccountPath,
        private string $googleOrigins,
    ) {}

    public function isAppleReady(): bool
    {
        if ($this->applePassTypeId === '' || $this->appleTeamId === '') {
            return false;
        }

        $cert = $this->resolvePath($this->appleCertPath);
        $wwdr = $this->resolvePath($this->appleWwdrPath);

        return $cert !== null && is_readable($cert) && $wwdr !== null && is_readable($wwdr);
    }

    public function isGoogleReady(): bool
    {
        if ($this->googleIssuerId === '') {
            return false;
        }

        $account = $this->resolvePath($this->googleServiceAccountPath);
        if ($account === null || !is_readable($account)) {
            return false;
        }

        $json = json_decode((string) file_get_contents($account), true);

        return is_array($json)
            && !empty($json['client_email'])
            && !empty($json['private_key']);
    }

    public function isAnyReady(): bool
    {
        return $this->isAppleReady() || $this->isGoogleReady();
    }

    public function applePassTypeId(): string
    {
        return $this->applePassTypeId;
    }

    public function appleTeamId(): string
    {
        return $this->appleTeamId;
    }

    public function appleCertPath(): ?string
    {
        return $this->resolvePath($this->appleCertPath);
    }

    public function appleCertPassword(): string
    {
        return $this->appleCertPassword;
    }

    public function appleWwdrPath(): ?string
    {
        return $this->resolvePath($this->appleWwdrPath);
    }

    public function googleIssuerId(): string
    {
        return $this->googleIssuerId;
    }

    /** @return array{client_email: string, private_key: string}|null */
    public function googleServiceAccount(): ?array
    {
        $path = $this->resolvePath($this->googleServiceAccountPath);
        if ($path === null || !is_readable($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        return [
            'client_email' => (string) $json['client_email'],
            'private_key' => (string) $json['private_key'],
        ];
    }

    /** @return list<string> */
    public function googleOrigins(): array
    {
        $parts = preg_split('/[\s,]+/', trim($this->googleOrigins)) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function resolvePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (!str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = rtrim($this->projectDir, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }

        return $path;
    }
}
