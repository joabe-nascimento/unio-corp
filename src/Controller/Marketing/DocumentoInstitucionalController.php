<?php

namespace App\Controller\Marketing;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Documento institucional Uniowork — PDF A4 via mPDF (capa full-bleed + marca d’água).
 */
class DocumentoInstitucionalController extends AbstractController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    #[Route('/documento-institucional', name: 'app_documento_institucional', methods: ['GET'])]
    public function pdf(): Response
    {
        $now = new \DateTimeImmutable('now');
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $logoPath = $this->resolveLogoPath($projectDir);

        $vars = [
            'doc_version' => '1.2',
            'doc_date' => $now->format('d/m/Y'),
            'doc_year' => $now->format('Y'),
            'logo_path' => $logoPath,
            'hub_categories' => $this->hubCategories(),
            'module_matrix' => $this->moduleMatrix(),
        ];

        $coverHtml = $this->twig->render('marketing/documento_institucional_pdf.html.twig', $vars + [
            'doc_section' => 'cover',
        ]);
        $bodyHtml = $this->twig->render('marketing/documento_institucional_pdf.html.twig', $vars + [
            'doc_section' => 'body',
        ]);

        $tmpDir = $projectDir . '/var/mpdf';
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException('Não foi possível criar o diretório temporário do mPDF.');
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'margin_header' => 0,
            'margin_footer' => 8,
            'tempDir' => $tmpDir,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle('Unio — Documento Institucional Uniowork');
        $mpdf->SetAuthor('Unio · uniowork.com.br');
        $mpdf->SetCreator('Unio · mPDF');
        $mpdf->SetSubject('Documento institucional confidencial · Uniowork');

        // Capa full-bleed
        $mpdf->AddPageByArray([
            'margin-left' => 0,
            'margin-right' => 0,
            'margin-top' => 0,
            'margin-bottom' => 0,
            'margin-header' => 0,
            'margin-footer' => 0,
        ]);
        $mpdf->SetHTMLFooter('');
        $mpdf->showWatermarkText = false;
        $mpdf->WriteHTML($coverHtml);

        // Páginas internas
        $mpdf->AddPageByArray([
            'margin-left' => 12,
            'margin-right' => 12,
            'margin-top' => 14,
            'margin-bottom' => 16,
            'margin-header' => 0,
            'margin-footer' => 8,
        ]);
        $mpdf->SetWatermarkText('UNIO · CONFIDENCIAL');
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'dejavusans';
        $mpdf->watermarkTextAlpha = 0.06;
        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size:7.5pt;color:#64748b;border-top:1px solid #e2e8f0;padding-top:3px;">
                <tr>
                    <td width="55%">Unio · Documento institucional · Confidencial</td>
                    <td width="45%" align="right">{PAGENO} / {nbpg} · uniowork.com.br</td>
                </tr>
            </table>
        ');
        $mpdf->WriteHTML($bodyHtml);

        $filename = sprintf('unio-documento-institucional-v%s-%s.pdf', '1-2', $now->format('Ymd'));

        return new Response(
            $mpdf->Output($filename, Destination::STRING_RETURN),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ]
        );
    }

    /**
     * Preferência PNG (mPDF renderiza melhor que SVG na capa).
     */
    private function resolveLogoPath(string $projectDir): string
    {
        foreach ([
            $projectDir . '/public/images/logos/unio-logotipo.png',
            $projectDir . '/public/images/logos/logotipo-unio.png',
            $projectDir . '/public/images/logos/u-logo.png',
            $projectDir . '/assets/unio-logotipo.png',
            $projectDir . '/assets/logotipo-unio.png',
            $projectDir . '/public/images/logos/logotipo.svg',
        ] as $candidate) {
            if (is_file($candidate)) {
                return str_replace('\\', '/', $candidate);
            }
        }

        return '';
    }

    /** @return list<array{title: string, hubs: list<string>}> */
    private function hubCategories(): array
    {
        return [
            ['title' => 'Comercial', 'hubs' => ['CRM', 'Leads', 'Pipeline kanban', 'Clientes', 'Atividades', 'Analytics']],
            ['title' => 'Entrega', 'hubs' => ['Projetos & Metas', 'Kanban de tarefas', 'Playbooks', 'Check-ins', 'Portal do cliente']],
            ['title' => 'Pessoas', 'hubs' => ['RH', 'Recrutamento', 'Admissões', 'Organograma', 'Portal do colaborador']],
            ['title' => 'Operação', 'hubs' => ['Núcleo TI', 'Integrações', 'Inovação', 'Alertas', 'Vitória']],
        ];
    }

    /** @return list<array{area: string, modules: string, status: string, outcome: string}> */
    private function moduleMatrix(): array
    {
        return [
            ['area' => 'CRM', 'modules' => 'Leads, pipeline, clientes, atividades, analytics', 'status' => 'Produção', 'outcome' => 'Funil comercial rastreável'],
            ['area' => 'Projetos', 'modules' => 'Kanban, metas, tarefas, painel de entregas', 'status' => 'Produção', 'outcome' => 'Entrega alinhada à venda'],
            ['area' => 'Portal', 'modules' => 'Portal do cliente, acompanhamento', 'status' => 'Produção', 'outcome' => 'Cliente no mesmo organismo'],
            ['area' => 'RH', 'modules' => 'Funcionários, admissão, férias, organograma', 'status' => 'Produção', 'outcome' => 'Time que opera a Unio'],
            ['area' => 'Recrutamento', 'modules' => 'Vagas, candidatos, pipeline DnD', 'status' => 'Produção', 'outcome' => 'Seleção no mesmo padrão kanban'],
            ['area' => 'TI', 'modules' => 'Chamados, SLA, knowledge base', 'status' => 'Produção', 'outcome' => 'Suporte à operação'],
            ['area' => 'Integrações', 'modules' => 'APIs, webhooks, conectores', 'status' => 'Produção', 'outcome' => 'Ecossistema extensível'],
            ['area' => 'Inovação', 'modules' => 'Kanban de iniciativas, radar', 'status' => 'Produção', 'outcome' => 'Backlog de evolução'],
            ['area' => 'Vitória', 'modules' => 'Assistente contextual', 'status' => 'Produção', 'outcome' => 'Copiloto com governança'],
            ['area' => 'Plataforma', 'modules' => 'Multi-empresa, grants, white-label', 'status' => 'Produção', 'outcome' => 'Isolamento e marca por unidade'],
        ];
    }
}
