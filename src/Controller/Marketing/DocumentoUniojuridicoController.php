<?php

namespace App\Controller\Marketing;

use App\Config\JuridicoModuleRegistry;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Documento institucional Unio Jurídico — PDF A4 via mPDF (capa full-bleed + marca d'água).
 */
class DocumentoUniojuridicoController extends AbstractController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    #[Route('/documento-institucional-juridico', name: 'app_documento_uniojuridico', methods: ['GET'])]
    public function pdf(): Response
    {
        $now = new \DateTimeImmutable('now');
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $logoPath = $this->resolveLogoPath($projectDir);

        $vars = [
            'doc_version' => '1.0',
            'doc_date' => $now->format('d/m/Y'),
            'doc_year' => $now->format('Y'),
            'logo_path' => $logoPath,
            'mapa_modulos' => $this->mapaModulos(),
            'total_modulos' => \count(JuridicoModuleRegistry::MODULES),
        ];

        $coverHtml = $this->twig->render('marketing/documento_uniojuridico_pdf.html.twig', $vars + [
            'doc_section' => 'cover',
        ]);
        $bodyHtml = $this->twig->render('marketing/documento_uniojuridico_pdf.html.twig', $vars + [
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

        $mpdf->SetTitle('Unio Jurídico — Documento Institucional');
        $mpdf->SetAuthor('Unio Jurídico · uniojuridico.uniowork.com.br');
        $mpdf->SetCreator('Unio · mPDF');
        $mpdf->SetSubject('Documento institucional confidencial · Unio Jurídico');

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
        $mpdf->SetWatermarkText('UNIO JURÍDICO · CONFIDENCIAL');
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'dejavusans';
        $mpdf->watermarkTextAlpha = 0.06;
        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size:7.5pt;color:#8a6a6f;border-top:1px solid #ecd9c9;padding-top:3px;">
                <tr>
                    <td width="55%">Unio Jurídico · Documento institucional · Confidencial</td>
                    <td width="45%" align="right">{PAGENO} / {nbpg} · uniojuridico.uniowork.com.br</td>
                </tr>
            </table>
        ');
        $mpdf->WriteHTML($bodyHtml);

        $filename = sprintf('unio-juridico-documento-institucional-v%s-%s.pdf', '1-0', $now->format('Ymd'));

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

    private function resolveLogoPath(string $projectDir): string
    {
        foreach ([
            $projectDir . '/public/images/logos/unio-juridico.png',
            $projectDir . '/public/images/logos/favicon-unio-juridico.png',
        ] as $candidate) {
            if (is_file($candidate)) {
                return str_replace('\\', '/', $candidate);
            }
        }

        return '';
    }

    /** @return list<array{key: string, label: string, modules: list<array{label: string, subtitle: string, status: string, status_label: string}>}> */
    private function mapaModulos(): array
    {
        $out = [];
        foreach (JuridicoModuleRegistry::grouped() as $grupo) {
            $out[] = [
                'key' => $grupo['key'],
                'label' => $grupo['label'],
                'modules' => array_map(static function (array $m): array {
                    return [
                        'label' => $m['label'],
                        'subtitle' => $m['subtitle'],
                        'status' => $m['status'],
                        'status_label' => JuridicoModuleRegistry::statusLabel($m['status']),
                    ];
                }, $grupo['modules']),
            ];
        }

        return $out;
    }
}
