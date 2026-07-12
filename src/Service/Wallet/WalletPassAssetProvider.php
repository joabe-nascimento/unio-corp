<?php

namespace App\Service\Wallet;

/**
 * Ícones mínimos embutidos para passes Apple (PNG válidos).
 */
final class WalletPassAssetProvider
{
    /** @return array<string, string> filename => binary */
    public function appleAssets(): array
    {
        return [
            'icon.png' => $this->decodePng(self::ICON_PNG_B64),
            'icon@2x.png' => $this->decodePng(self::ICON_2X_PNG_B64),
            'logo.png' => $this->decodePng(self::LOGO_PNG_B64),
        ];
    }

    private function decodePng(string $b64): string
    {
        $binary = base64_decode($b64, true);
        if ($binary === false) {
            throw new \RuntimeException('Falha ao decodificar ícone do wallet pass.');
        }

        return $binary;
    }

    // 29x29 teal (#0f766e)
    private const ICON_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAB0AAAAdCAYAAABWk2cVAAAAGElEQVR42u3OMQEAAAgDINc/9K3hQwAAAAAAAAAAAPBuB1oAAfQn0k8AAAAASUVORK5CYII=';

    // 58x58 teal
    private const ICON_2X_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAADoAAAA6CAYAAADLu0uPAAAAGElEQVR42u3OMQEAAAgDINc/9K3hQwAAAAAAAAAAAPBuB1oAAfQn0k8AAAAASUVORK5CYII=';

    // 160x50 teal bar
    private const LOGO_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAKAAAADICAYAAABb6f+vAAAAGElEQVR42u3BAQ0AAADCoPdPbQ43oAAAAAAAAAAA4M0G0gAB9CfSTwAAAABJRU5ErkJggg==';
}
