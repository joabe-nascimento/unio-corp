<?php

namespace App\Service\Juridico;

use App\Entity\JuridicoCliente;
use App\Entity\JuridicoDocumento;
use App\Entity\JuridicoProcesso;
use App\Repository\JuridicoDocumentoRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\JuridicoProcessoRepository;

/**
 * Visão do portal do cliente: timeline simplificada, processos e documentos compartilhados.
 */
final class JuridicoPortalService
{
    public function __construct(
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoDocumentoRepository $documentoRepo,
    ) {
    }

    /**
     * @return array{
     *     processos: list<JuridicoProcesso>,
     *     documentos: list<JuridicoDocumento>,
     *     timeline: list<array{data: \DateTimeImmutable, titulo: string, texto: string, icone: string, tom: string}>
     * }
     */
    public function buildView(JuridicoCliente $cliente): array
    {
        $processos = $this->processoRepo->findBy(['cliente' => $cliente, 'empresa' => $cliente->getEmpresa()], ['atualizadoEm' => 'DESC']);
        $documentos = $this->documentoRepo->findVisiveisPortal($cliente->getEmpresa(), $cliente);
        $timeline = $this->montarTimeline($cliente, $processos);

        return [
            'processos' => $processos,
            'documentos' => $documentos,
            'timeline' => $timeline,
        ];
    }

    /**
     * @param list<JuridicoProcesso> $processos
     *
     * @return list<array{data: \DateTimeImmutable, titulo: string, texto: string, icone: string, tom: string}>
     */
    private function montarTimeline(JuridicoCliente $cliente, array $processos): array
    {
        $eventos = [];

        foreach ($processos as $processo) {
            $faseLabel = ucfirst(str_replace('_', ' ', $processo->getFase()));
            $eventos[] = [
                'data' => $processo->getAtualizadoEm() ?? $processo->getCriadoEm(),
                'titulo' => $processo->getNumero(),
                'texto' => sprintf('Processo em fase de %s · status %s', $faseLabel, $processo->getStatus()),
                'icone' => 'fa-scale-balanced',
                'tom' => $processo->getStatus() === JuridicoProcesso::STATUS_CRITICO ? 'warning' : 'info',
            ];

            foreach ($this->prazoRepo->findForEmpresa($cliente->getEmpresa(), 'pendentes') as $prazo) {
                if ($prazo->getProcesso()?->getId() !== $processo->getId()) {
                    continue;
                }
                $dias = $prazo->getDiasRestantes();
                $eventos[] = [
                    'data' => $prazo->getDataLimite(),
                    'titulo' => $prazo->getTipo(),
                    'texto' => $dias < 0
                        ? sprintf('Prazo vencido há %d dia(s) no processo %s', abs($dias), $processo->getNumero())
                        : sprintf('Prazo em %d dia(s) (%s) — %s', $dias, $prazo->getDataLimite()->format('d/m/Y'), $processo->getNumero()),
                    'icone' => 'fa-hourglass-half',
                    'tom' => $dias <= 3 ? 'danger' : 'warning',
                ];
            }
        }

        usort($eventos, static fn (array $a, array $b) => $b['data'] <=> $a['data']);

        return \array_slice($eventos, 0, 20);
    }
}
