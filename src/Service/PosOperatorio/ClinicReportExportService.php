<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioEvento;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioEventoRepository;
use App\Repository\PosOperatorioQuestionarioRespostaRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ClinicReportExportService
{
    public function __construct(
        private PosOperatorioQuestionarioRespostaRepository $questionarios,
        private PosOperatorioAlertaRepository $alertas,
        private PosOperatorioEventoRepository $eventos,
    ) {}

    public function exportQuestionarios(Empresa $empresa, int $days = 90): StreamedResponse
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', max(1, $days)));
        $rows = $this->questionarios->findByEmpresaSince($empresa, $since);

        return $this->csvResponse(
            sprintf('questionarios-%s.csv', date('Y-m-d')),
            ['Paciente', 'Código', 'Data ref.', 'Respondido em', 'Score', 'Alerta', 'Dor', 'Febre', 'Náusea'],
            function () use ($rows): \Generator {
                foreach ($rows as $qr) {
                    $p = $qr->getPaciente();
                    $r = $qr->getRespostas();
                    yield [
                        $p->getNome(),
                        $p->getCodigo(),
                        $qr->getDataReferencia()->format('d/m/Y'),
                        $qr->getRespondidoEm()->format('d/m/Y H:i'),
                        (string) $qr->getScoreRisco(),
                        $qr->isAlertaGerado() ? 'Sim' : 'Não',
                        (string) ($r['dor'] ?? $r['nivel_dor'] ?? ''),
                        (string) ($r['febre'] ?? $r['temperatura'] ?? ''),
                        (string) ($r['nausea'] ?? ''),
                    ];
                }
            },
        );
    }

    public function exportAlertas(Empresa $empresa): StreamedResponse
    {
        $rows = $this->alertas->findForExportByEmpresa($empresa);

        return $this->csvResponse(
            sprintf('alertas-%s.csv', date('Y-m-d')),
            ['Paciente', 'Código', 'Prioridade', 'Motivo', 'Status', 'Criado em', 'Resolvido em', 'Responsável'],
            function () use ($rows): \Generator {
                foreach ($rows as $a) {
                    $p = $a->getPaciente();
                    yield [
                        $p->getNome(),
                        $p->getCodigo(),
                        $a->getPrioridade(),
                        $a->getMotivo(),
                        $a->getStatus(),
                        $a->getCriadoEm()->format('d/m/Y H:i'),
                        $a->getResolvidoEm()?->format('d/m/Y H:i') ?? '',
                        $a->getResponsavel()?->getNome() ?? '',
                    ];
                }
            },
        );
    }

    public function exportAuditoria(Empresa $empresa): StreamedResponse
    {
        $eventos = $this->eventos->findRecentByEmpresa($empresa, 500);
        $auditTypes = [
            PosOperatorioEvento::TIPO_ACESSO_FICHA,
            PosOperatorioEvento::TIPO_CONSENTIMENTO,
            PosOperatorioEvento::TIPO_CADASTRO,
        ];

        return $this->csvResponse(
            sprintf('auditoria-lgpd-%s.csv', date('Y-m-d')),
            ['Tipo', 'Paciente', 'Detalhe', 'Autor', 'Em'],
            function () use ($eventos, $auditTypes): \Generator {
                foreach ($eventos as $ev) {
                    if (!\in_array($ev->getTipo(), $auditTypes, true)) {
                        continue;
                    }
                    $label = match ($ev->getTipo()) {
                        PosOperatorioEvento::TIPO_ACESSO_FICHA => 'Acesso à ficha',
                        PosOperatorioEvento::TIPO_CONSENTIMENTO => 'Consentimento LGPD',
                        default => 'Cadastro',
                    };
                    yield [
                        $label,
                        $ev->getPaciente()->getCodigo(),
                        $ev->getDescricao(),
                        $ev->getAutor()?->getNome() ?? 'Sistema',
                        $ev->getCriadoEm()->format('d/m/Y H:i'),
                    ];
                }
            },
        );
    }

    /**
     * @param list<string> $header
     * @param callable(): \Generator<int, list<string>> $rows
     */
    private function csvResponse(string $filename, array $header, callable $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows() as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
