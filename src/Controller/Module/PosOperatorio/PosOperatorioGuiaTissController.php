<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicGuiaTiss;
use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicContaService;
use App\Service\PosOperatorio\ClinicGuiaTissService;
use App\Service\PosOperatorio\ClinicTissXmlExporter;
use App\Service\PosOperatorio\ClinicTussCatalogService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/guias')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioGuiaTissController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicGuiaTissService $guias,
        private ClinicContaService $contas,
        private ClinicTussCatalogService $tuss,
        private ClinicTissXmlExporter $xml,
    ) {}

    #[Route('', name: 'app_pos_operatorio_guias', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = $request->query->getString('status', 'todos');
        if ($status !== 'todos' && !\in_array($status, ClinicGuiaTiss::STATUSES, true)) {
            $status = 'todos';
        }

        $filtro = $status === 'todos' ? null : $status;
        $guias = $this->guias->list($empresa, $filtro);
        $listLimit = $this->guias->listLimit();
        $listTotal = $this->guias->countList($empresa, $filtro);

        return $this->render('modules/pos-operatorio/guias/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'guias',
            'filtro_status' => $status,
            'guias' => $guias,
            'status_labels' => ClinicGuiaTissService::statusLabels(),
            'list_total' => $listTotal,
            'list_limit' => $listLimit,
            'list_truncated' => $listTotal > $listLimit,
        ]);
    }

    #[Route('/tuss/search', name: 'app_pos_operatorio_guias_tuss_search', methods: ['GET'])]
    public function tussSearch(Request $request): JsonResponse
    {
        $this->requireEmpresa();
        $q = $request->query->getString('q');

        return $this->json([
            'items' => $this->tuss->search($q, 20),
        ]);
    }

    #[Route('/conta/{contaId}', name: 'app_pos_operatorio_guias_from_conta', requirements: ['contaId' => '\d+'], methods: ['POST'])]
    public function fromConta(int $contaId, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $conta = $this->contas->findForEmpresa($empresa, $contaId);
        if ($conta === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_conta_guia_'.$contaId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_contas', ['status' => 'aberto']);
        }

        try {
            $convenio = $this->guias->requireConvenio($empresa, $request->request->getInt('convenio_id'));
            $guia = $this->guias->convertContaToConvenio(
                $conta,
                $empresa,
                $convenio,
                $request->request->getString('numero_guia') ?: null,
            );
            $this->addFlash('success', 'Guia de convênio criada.');

            return $this->redirectToRoute('app_pos_operatorio_guias_show', ['id' => $guia->getId()]);
        } catch (\Throwable $e) {
            $existing = $this->guias->findByConta($empresa, $conta);
            if ($existing !== null) {
                $this->addFlash('info', $e->getMessage());

                return $this->redirectToRoute('app_pos_operatorio_guias_show', ['id' => $existing->getId()]);
            }
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_pos_operatorio_contas', ['status' => 'aberto']);
        }
    }

    #[Route('/{id}/xml', name: 'app_pos_operatorio_guias_xml', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function xml(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $guia = $this->guias->findForEmpresa($empresa, $id);
        if ($guia === null) {
            throw $this->createNotFoundException();
        }

        try {
            $content = $this->xml->exportGuia($guia);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_pos_operatorio_guias_show', ['id' => $id]);
        }

        $filename = sprintf('tiss-guia-%s.xml', preg_replace('/[^a-zA-Z0-9_-]+/', '-', $guia->getNumeroGuia()) ?: $id);

        return new Response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_guias_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $guia = $this->guias->findForEmpresa($empresa, $id);
        if ($guia === null) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_guia_'.$id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_guias_show', ['id' => $id]);
            }

            $action = $request->request->getString('action');
            try {
                match ($action) {
                    'salvar' => $this->guias->updateCabecalho($guia, $empresa, [
                        'numero_guia' => $request->request->getString('numero_guia'),
                        'senha_autorizacao' => $request->request->getString('senha_autorizacao'),
                    ]),
                    'add_item' => $this->guias->addItem($guia, $empresa, [
                        'codigo_tuss' => $request->request->getString('codigo_tuss'),
                        'descricao' => $request->request->getString('descricao'),
                        'quantidade' => $request->request->getInt('quantidade', 1),
                        'valor_centavos' => $this->parseCentavos($request->request->getString('valor')),
                    ]),
                    'remove_item' => $this->guias->removeItem($guia, $empresa, $request->request->getInt('item_id')),
                    'status' => $this->guias->changeStatus(
                        $guia,
                        $empresa,
                        $request->request->getString('status'),
                        $request->request->getString('motivo_glosa') ?: null,
                    ),
                    'reabrir_glosa' => $this->guias->reabrirAposGlosa($guia, $empresa),
                    default => throw new \InvalidArgumentException('Ação inválida.'),
                };
                $this->addFlash('success', $action === 'reabrir_glosa'
                    ? 'Guia reaberta para reapresentação.'
                    : 'Guia atualizada.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_guias_show', ['id' => $id]);
        }

        return $this->render('modules/pos-operatorio/guias/show.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'guias',
            'guia' => $guia,
            'status_labels' => ClinicGuiaTissService::statusLabels(),
            'next_statuses' => ClinicGuiaTiss::allowedTransitionsFrom($guia->getStatus()),
            'tuss_search_url' => $this->generateUrl('app_pos_operatorio_guias_tuss_search'),
        ]);
    }

    private function parseCentavos(string $raw): ?int
    {
        $valorRaw = trim($raw);
        if ($valorRaw === '') {
            return null;
        }
        $normalized = str_replace(['.', ' '], ['', ''], $valorRaw);
        $normalized = str_replace(',', '.', $normalized);
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException('Valor inválido.');
        }

        return (int) round(((float) $normalized) * 100);
    }

    private function requireEmpresa(): Empresa
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
