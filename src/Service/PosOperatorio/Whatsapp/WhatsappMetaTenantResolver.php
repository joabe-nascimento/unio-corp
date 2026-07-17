<?php

namespace App\Service\PosOperatorio\Whatsapp;

use App\Entity\Empresa;
use App\Repository\EmpresaRepository;

/**
 * Resolve de forma explícita o número Meta recebido para a clínica dona do canal.
 */
final class WhatsappMetaTenantResolver
{
    public function __construct(
        private EmpresaRepository $empresas,
        private string $metaPhoneNumberId = '',
        private string $metaEmpresaCnpj = '',
        private string $metaTenantMapJson = '',
    ) {}

    public function resolve(string $phoneNumberId): ?Empresa
    {
        $phoneNumberId = trim($phoneNumberId);
        if ($phoneNumberId === '') {
            return null;
        }

        $map = json_decode($this->metaTenantMapJson, true);
        if (\is_array($map)) {
            $cnpj = $this->normalizeCnpj((string) ($map[$phoneNumberId] ?? ''));
            if ($cnpj !== '') {
                return $this->findEmpresaByCnpj($cnpj);
            }
        }

        if ($this->metaPhoneNumberId === '' || !hash_equals($this->metaPhoneNumberId, $phoneNumberId)) {
            return null;
        }

        $cnpj = $this->normalizeCnpj($this->metaEmpresaCnpj);

        return $cnpj !== '' ? $this->findEmpresaByCnpj($cnpj) : null;
    }

    private function findEmpresaByCnpj(string $cnpj): ?Empresa
    {
        $empresa = $this->empresas->findOneBy(['cnpj' => $cnpj, 'ativo' => true]);
        if ($empresa instanceof Empresa) {
            return $empresa;
        }

        // Compatibilidade com cadastros antigos que armazenam pontuação.
        foreach ($this->empresas->findBy(['ativo' => true]) as $candidate) {
            if ($this->normalizeCnpj((string) $candidate->getCnpj()) === $cnpj) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeCnpj(string $cnpj): string
    {
        return preg_replace('/\D+/', '', $cnpj) ?? '';
    }
}
