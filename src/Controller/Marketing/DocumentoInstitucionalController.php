<?php

namespace App\Controller\Marketing;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Documento institucional Uniowork — PDF A4 via mPDF (capa + marca d’água).
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
        $html = $this->twig->render('marketing/documento_institucional_pdf.html.twig', [
            'doc_version' => '1.0',
            'doc_date' => $now->format('d/m/Y'),
            'doc_year' => $now->format('Y'),
            'hub_categories' => $this->hubCategories(),
        ]);

        $tmpDir = $this->getParameter('kernel.project_dir') . '/var/mpdf';
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException('Não foi possível criar o diretório temporário do mPDF.');
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 16,
            'margin_bottom' => 18,
            'margin_header' => 8,
            'margin_footer' => 10,
            'tempDir' => $tmpDir,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle('Uniowork — Documento Institucional da Plataforma');
        $mpdf->SetAuthor('Unio · uniowork.com.br');
        $mpdf->SetCreator('Uniowork · mPDF');
        $mpdf->SetSubject('Documento institucional confidencial');

        $mpdf->SetWatermarkText('UNIOWORK · CONFIDENCIAL');
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'dejavusans';
        $mpdf->watermarkTextAlpha = 0.07;

        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size:8pt;color:#64748b;border-top:1px solid #e2e8f0;padding-top:4px;">
                <tr>
                    <td width="50%">Uniowork · Documento institucional · Confidencial</td>
                    <td width="50%" align="right">Página {PAGENO} de {nbpg}</td>
                </tr>
            </table>
        ');

        $mpdf->WriteHTML($html);

        $filename = sprintf('uniowork-documento-institucional-v1-%s.pdf', $now->format('Ymd'));

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

    /** @return list<array{title: string, hubs: list<string>}> */
    private function hubCategories(): array
    {
        return [
            ['title' => 'Pessoas & RH', 'hubs' => ['Operações', 'Talentos', 'Maturidade', 'Clima', 'Portal do Colaborador', 'Recrutamento']],
            ['title' => 'Negócios & Growth', 'hubs' => ['Comercial (CRM)', 'Projetos & Metas', 'Benefícios', 'Academy', 'Customer Success', 'Marketing']],
            ['title' => 'Tecnologia', 'hubs' => ['Núcleo TI', 'Integrações', 'Inovação', 'Segurança da Informação']],
            ['title' => 'Finanças & Compliance', 'hubs' => ['Financeiro', 'Jurídico', 'Compliance', 'Licitações']],
            ['title' => 'Operações & Ativos', 'hubs' => ['Obras', 'Suprimentos', 'Facilities', 'Qualidade', 'PMO']],
            ['title' => 'Inteligência', 'hubs' => ['Analytics', 'Conhecimento', 'Data & Lakehouse', 'Unio Cortex']],
        ];
    }
}
