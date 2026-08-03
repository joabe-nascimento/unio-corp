<?php

namespace App\Controller\Module\Juridico;

use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoDocumentoRepository;
use App\Service\Juridico\JuridicoDocumentoService;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/documentos')]
#[IsGranted('ROLE_USER')]
class JuridicoDocumentoController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoDocumentoService $documentos,
        private JuridicoProcessoService $processos,
        private JuridicoDocumentoRepository $documentoRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_documentos')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $categoria = (string) $request->query->get('categoria', '');
        $q = (string) $request->query->get('q', '');

        return $this->render('modules/juridico/documentos_list.html.twig', [
            'documentos' => $this->documentos->findForEmpresa($empresa, $categoria ?: null, $q ?: null),
            'processos' => $this->processos->listForSelect($empresa),
            'rag_sincronizados' => $this->documentoRepo->countRagSincronizados($empresa),
            'filter_categoria' => $categoria,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_documento_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_documentos', ['open_novo' => 1]);
        }

        $file = $request->files->get('arquivo');
        /** @var User|null $user */
        $user = $this->getUser();

        try {
            $this->requireCsrf($request, 'juridico_documento_form');
            if (!$file) {
                throw new JuridicoProcessException('Selecione um arquivo para enviar.');
            }
            $this->documentos->create($empresa, $request->request->all(), $file, $user);
            $this->addFlash('success', 'Documento enviado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_documentos');
    }

    #[Route('/{id}/download', name: 'app_juridico_documento_download', requirements: ['id' => '\d+'])]
    public function download(int $id): BinaryFileResponse
    {
        $empresa = $this->requireEmpresa();
        $documento = $this->documentos->loadForEmpresa($empresa, $id);
        $path = $this->documentos->resolveAbsolutePath($documento);

        if (!is_file($path)) {
            throw $this->createNotFoundException('Arquivo não encontrado.');
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $documento->getNome());
        if ($documento->getMimeType()) {
            $response->headers->set('Content-Type', $documento->getMimeType());
        }

        return $response;
    }

    #[Route('/{id}/excluir', name: 'app_juridico_documento_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $documento = $this->documentos->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_documento_excluir_' . $id);
        $this->documentos->delete($documento);
        $this->addFlash('success', 'Documento excluído.');

        return $this->redirectToRoute('app_juridico_documentos');
    }
}
