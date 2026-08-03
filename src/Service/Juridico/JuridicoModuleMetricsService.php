<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoCobrancaRepository;
use App\Repository\JuridicoDocumentoRepository;
use App\Repository\JuridicoHonorarioLancamentoRepository;
use App\Repository\JuridicoPrazoRepository;

/**
 * KPIs dinâmicos por módulo jurídico — usados nas listagens e na vitrine "Sobre o módulo".
 */
final class JuridicoModuleMetricsService
{
    public function __construct(
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoDocumentoRepository $documentoRepo,
        private JuridicoClienteRepository $clienteRepo,
        private JuridicoHonorarioLancamentoRepository $honorarioRepo,
        private JuridicoCobrancaRepository $cobrancaRepo,
    ) {
    }

    /** @return array{vencem_hoje: int, criticos: int, pendentes: int, cumprimento: float} */
    public function prazos(Empresa $empresa): array
    {
        $sla = $this->prazoRepo->slaGrupo([$empresa]);

        return [
            'vencem_hoje' => $this->prazoRepo->countVencemHoje($empresa),
            'criticos' => $this->prazoRepo->countCriticosByEmpresa($empresa),
            'pendentes' => $this->prazoRepo->countPendentes($empresa),
            'cumprimento' => $sla['taxa'],
        ];
    }

    /** @return array{total: int, rag_sincronizados: int, visivel_portal: int} */
    public function documentos(Empresa $empresa): array
    {
        return [
            'total' => $this->documentoRepo->countByEmpresa($empresa),
            'rag_sincronizados' => $this->documentoRepo->countRagSincronizados($empresa),
            'visivel_portal' => $this->documentoRepo->countVisivelPortal($empresa),
        ];
    }

    /** @return array{clientes_portal: int, convites_pendentes: int, docs_compartilhados: int} */
    public function portal(Empresa $empresa): array
    {
        $docs = $this->documentos($empresa);

        return [
            'clientes_portal' => $this->clienteRepo->countComPortalAtivo($empresa),
            'convites_pendentes' => $this->clienteRepo->countConvitesPendentes($empresa),
            'docs_compartilhados' => $docs['visivel_portal'],
        ];
    }

    /** @return array{horas_mes: float, receita_mes: float, lancamentos_mes: int} */
    public function honorarios(Empresa $empresa): array
    {
        $mes = (new \DateTimeImmutable('today'))->format('Y-m');

        return [
            'horas_mes' => $this->honorarioRepo->sumHorasMes($empresa, $mes),
            'receita_mes' => $this->honorarioRepo->sumReceitaMes($empresa, $mes),
            'lancamentos_mes' => \count($this->honorarioRepo->findForEmpresa($empresa, null, $mes)),
        ];
    }

    /** @return array{em_aberto: float, vencidos: int, recebido_mes: float} */
    public function cobranca(Empresa $empresa): array
    {
        $empresas = [$empresa];

        return [
            'em_aberto' => $this->cobrancaRepo->sumAberto($empresas),
            'vencidos' => $this->cobrancaRepo->countVencidas($empresas),
            'recebido_mes' => $this->cobrancaRepo->sumRecebidoMes($empresas),
        ];
    }

    /**
     * KPIs formatados para a vitrine do módulo (substitui "—" do catálogo).
     *
     * @return list<array{label: string, value: string}>
     */
    public function paraModulo(string $slug, Empresa $empresa): array
    {
        return match ($slug) {
            'prazos' => $this->formatPrazos($empresa),
            'documentos' => $this->formatDocumentos($empresa),
            'portal' => $this->formatPortal($empresa),
            'honorarios' => $this->formatHonorarios($empresa),
            'cobranca' => $this->formatCobranca($empresa),
            default => [],
        };
    }

    /** @return list<array{label: string, value: string}> */
    private function formatPrazos(Empresa $empresa): array
    {
        $m = $this->prazos($empresa);

        return [
            ['label' => 'Vencem hoje', 'value' => (string) $m['vencem_hoje']],
            ['label' => 'Críticos (48h)', 'value' => (string) $m['criticos']],
            ['label' => 'Cumprimento', 'value' => $m['cumprimento'] . '%'],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function formatDocumentos(Empresa $empresa): array
    {
        $m = $this->documentos($empresa);

        return [
            ['label' => 'Documentos', 'value' => (string) $m['total']],
            ['label' => 'Indexados (RAG)', 'value' => (string) $m['rag_sincronizados']],
            ['label' => 'No portal', 'value' => (string) $m['visivel_portal']],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function formatPortal(Empresa $empresa): array
    {
        $m = $this->portal($empresa);

        return [
            ['label' => 'Clientes ativos', 'value' => (string) $m['clientes_portal']],
            ['label' => 'Convites pendentes', 'value' => (string) $m['convites_pendentes']],
            ['label' => 'Docs compartilhados', 'value' => (string) $m['docs_compartilhados']],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function formatHonorarios(Empresa $empresa): array
    {
        $m = $this->honorarios($empresa);

        return [
            ['label' => 'Horas/mês', 'value' => number_format($m['horas_mes'], 1, ',', '.') . 'h'],
            ['label' => 'Receita/mês', 'value' => 'R$ ' . number_format($m['receita_mes'], 0, ',', '.')],
            ['label' => 'Lançamentos', 'value' => (string) $m['lancamentos_mes']],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function formatCobranca(Empresa $empresa): array
    {
        $m = $this->cobranca($empresa);

        return [
            ['label' => 'Em aberto', 'value' => 'R$ ' . number_format($m['em_aberto'], 0, ',', '.')],
            ['label' => 'Vencidos', 'value' => (string) $m['vencidos']],
            ['label' => 'Recebido/mês', 'value' => 'R$ ' . number_format($m['recebido_mes'], 0, ',', '.')],
        ];
    }
}
