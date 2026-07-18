<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\Clinic\ClinicTrilhaImportService;
use App\Service\PosOperatorio\PosOperatorioProtocoloService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/pacientes/importar')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioPacienteImportController extends AbstractController
{
    private const T = 'modules/pos-operatorio/pacientes/';

    public function __construct(
        private WorkspaceService $workspace,
        private ClinicTrilhaImportService $importService,
        private PosOperatorioProtocoloService $protocoloService,
    ) {}

    #[Route('', name: 'app_pos_operatorio_pacientes_importar', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        $result = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('pos_op_import_trilha', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');
            } else {
                $content = $this->readUpload($request);
                if ($content === null) {
                    $this->addFlash('error', 'Envie um arquivo CSV (.csv) ou cole o conteúdo da planilha.');
                } else {
                    /** @var User $user */
                    $user = $this->getUser();
                    $result = $this->importService->importCsv($empresa, $content, $user);

                    if (!($result['ok'] ?? false)) {
                        $this->addFlash('error', (string) ($result['error'] ?? 'Falha na importação.'));
                    } elseif (($result['importados'] ?? 0) > 0) {
                        $msg = sprintf('%d paciente(s) importado(s) na Trilha Unio.', $result['importados']);
                        if (!empty($result['erros'])) {
                            $msg .= sprintf(' %d linha(s) com erro.', \count($result['erros']));
                        }
                        $this->addFlash('success', $msg);
                    } elseif (!empty($result['erros'])) {
                        $this->addFlash('error', 'Nenhum paciente importado. Revise os erros abaixo.');
                    }
                }
            }
        }

        $this->protocoloService->ensureLibraryProtocols($empresa);

        return $this->render(self::T . 'importar.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'pacientes',
            'protocolos' => $this->protocoloService->listByEmpresa($empresa),
            'template_headers' => ClinicTrilhaImportService::TEMPLATE_HEADERS,
            'result' => $result,
        ]);
    }

    #[Route('/modelo.csv', name: 'app_pos_operatorio_pacientes_importar_modelo', methods: ['GET'])]
    public function modelo(): Response
    {
        $this->requireEmpresa();

        $csv = $this->importService->templateCsv();
        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="trilha-import-pacientes.csv"',
        );

        return $response;
    }

    private function readUpload(Request $request): ?string
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('arquivo');
        if ($file instanceof UploadedFile && $file->isValid()) {
            $content = file_get_contents($file->getPathname());

            return $content !== false ? $content : null;
        }

        $paste = trim((string) $request->request->get('csv', ''));

        return $paste !== '' ? $paste : null;
    }

    private function requireEmpresa(): Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
