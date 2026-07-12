<?php

namespace App\Wallet;

enum WalletPassType: string
{
    case Carteirinha = 'carteirinha';
    case Comprovante = 'comprovante';

    public function label(): string
    {
        return match ($this) {
            self::Carteirinha => 'Carteirinha digital',
            self::Comprovante => 'Comprovante de procedimento',
        };
    }

    public function appleFilename(): string
    {
        return match ($this) {
            self::Carteirinha => 'carteirinha-uniosaude.pkpass',
            self::Comprovante => 'comprovante-uniosaude.pkpass',
        };
    }

    public function googleClassSuffix(): string
    {
        return match ($this) {
            self::Carteirinha => 'uniosaude_carteirinha',
            self::Comprovante => 'uniosaude_comprovante',
        };
    }

    public static function tryFromRoute(string $value): ?self
    {
        return self::tryFrom(strtolower(trim($value)));
    }
}
