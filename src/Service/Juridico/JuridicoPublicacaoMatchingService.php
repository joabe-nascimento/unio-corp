<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoCliente;
use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoPublicacao;
use App\Repository\JuridicoProcessoRepository;

/**
 * Vincula publicações DJEN a processos do escritório e aplica regras de prioridade.
 */
final class JuridicoPublicacaoMatchingService
{
    private const VALOR_ALTO = 100_000.0;

    public function __construct(
        private JuridicoProcessoRepository $processoRepo,
    ) {
    }

    public function aplicar(JuridicoPublicacao $publicacao): void
    {
        $norm = $publicacao->getNumeroProcessoNorm();
        if ($norm === null || $norm === '') {
            return;
        }

        $processo = $this->processoRepo->findByNumeroNorm($publicacao->getEmpresa(), $norm);
        if ($processo === null) {
            return;
        }

        $publicacao->setProcesso($processo);
        if ($processo->getCliente() !== null) {
            $publicacao->setCliente($processo->getCliente());
        }

        if ($publicacao->getStatus() === JuridicoPublicacao::STATUS_NAO_LIDA) {
            $publicacao->setStatus(JuridicoPublicacao::STATUS_VINCULADA);
        }

        $publicacao->setPrioridade($this->calcularPrioridade($processo, $publicacao));
    }

    private function calcularPrioridade(JuridicoProcesso $processo, JuridicoPublicacao $publicacao): string
    {
        if ($processo->getStatus() === JuridicoProcesso::STATUS_CRITICO) {
            return JuridicoPublicacao::PRIORIDADE_CRITICA;
        }

        $cliente = $processo->getCliente();
        if ($cliente !== null && $cliente->getStatus() === JuridicoCliente::STATUS_PREMIUM) {
            return JuridicoPublicacao::PRIORIDADE_ALTA;
        }

        $valor = (float) ($processo->getValor() ?? 0);
        if ($valor >= self::VALOR_ALTO) {
            return JuridicoPublicacao::PRIORIDADE_ALTA;
        }

        $tipo = mb_strtolower((string) $publicacao->getTipoComunicacao());
        if (str_contains($tipo, 'intima') || str_contains($tipo, 'cita')) {
            return JuridicoPublicacao::PRIORIDADE_ALTA;
        }

        return JuridicoPublicacao::PRIORIDADE_NORMAL;
    }
}
