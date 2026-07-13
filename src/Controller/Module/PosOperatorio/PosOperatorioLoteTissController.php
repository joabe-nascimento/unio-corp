<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicLoteTiss;
use App\Entity\Empresa;
use App\Entity\User;
use App\Http\RequestInts;
use App\Repository\ClinicConvenioRepository;
use App\Service\PosOperatorio\ClinicLoteTissService;
use App\Service\PosOperatorio\ClinicTissXmlExporter;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/lotes')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioLoteTissController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicLoteTissService $lotes,
        private ClinicConvenioRepository $convenios,
        private ClinicTissXmlExporter $xml,
    ) {}

    #[Route('', name: 'app_pos_operatorio_lotes', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_lote_create', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_lotes');
            }
            try {
                $convenioId = RequestInts::positiveOrNull($request->request->get('convenio_id'));
                if ($convenioId === null) {
                    throw new \InvalidArgumentException('Selecione o convênio.');
                }
                $convenio = $this->lotes->requireConvenio($empresa, $convenioId);
                $lote = $this->lotes->create(
                    $empresa,
                    $convenio,
                    $request->request->getString('competencia') ?: null,
                    $request->request->getString('numero') ?: null,
                );
                $this->addFlash('success', 'Lote criado.');

                return $this->redirectToRoute('app_pos_operatorio_lotes_show', ['id' => $lote->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_pos_operatorio_lotes');
            }
        }

        $status = $request->query->getString('status', 'todos');
        if ($status !== 'todos' && !\in_array($status, ClinicLoteTiss::STATUSES, true)) {
            $status = 'todos';
        }
        $filtro = $status === 'todos' ? null : $status;
        $lista = $this->lotes->list($empresa, $filtro);
        $listLimit = $this->lotes->listLimit();
        $listTotal = $this->lotes->countList($empresa, $filtro);

        return $this->render('modules/pos-operatorio/lotes/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'lotes',
            'filtro_status' => $status,
            'lotes' => $lista,
            'status_labels' => ClinicLoteTissService::statusLabels(),
            'convenios' => $this->convenios->findByEmpresa($empresa, true),
            'list_total' => $listTotal,
            'list_limit' => $listLimit,
            'list_truncated' => $listTotal > $listLimit,
            'competencia_default' => (new \DateTimeImmutable())->format('Y-m'),
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_lotes_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $lote = $this->lotes->findForEmpresa($empresa, $id);
        if ($lote === null) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_lote_'.$id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_lotes_show', ['id' => $id]);
            }

            $action = $request->request->getString('action');
            try {
                match ($action) {
                    'add_guia' => $this->lotes->addGuia(
                        $lote,
                        $empresa,
                        RequestInts::positiveOrNull($request->request->get('guia_id'))
                            ?? throw new \InvalidArgumentException('Selecione a guia.'),
                    ),
                    'remove_guia' => $this->lotes->removeGuia(
                        $lote,
                        $empresa,
                        RequestInts::positiveOrNull($request->request->get('guia_id'))
                            ?? throw new \InvalidArgumentException('Guia inválida.'),
                    ),
                    'fechar' => $this->lotes->fechar($lote, $empresa),
                    'marcar_enviado' => $this->lotes->marcarEnviado($lote, $empresa),
                    default => throw new \InvalidArgumentException('Ação inválida.'),
                };
                $this->addFlash('success', 'Lote atualizado.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_lotes_show', ['id' => $id]);
        }

        return $this->render('modules/pos-operatorio/lotes/show.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'lotes',
            'lote' => $lote,
            'status_labels' => ClinicLoteTissService::statusLabels(),
            'guia_status_labels' => \App\Service\PosOperatorio\ClinicGuiaTissService::statusLabels(),
            'elegiveis' => $lote->isAberto() ? $this->lotes->guiasElegiveis($empresa, $lote) : [],
        ]);
    }

    #[Route('/{id}/xml', name: 'app_pos_operatorio_lotes_xml', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function xml(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $lote = $this->lotes->findForEmpresa($empresa, $id);
        if ($lote === null) {
            throw $this->createNotFoundException();
        }
        if (!$lote->canExportXml()) {
            $this->addFlash('error', 'Feche o lote com guias antes de baixar o XML.');

            return $this->redirectToRoute('app_pos_operatorio_lotes_show', ['id' => $id]);
        }

        try {
            $content = $this->xml->exportLote($lote);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_pos_operatorio_lotes_show', ['id' => $id]);
        }

        $filename = sprintf('tiss-lote-%s.xml', preg_replace('/[^a-zA-Z0-9_-]+/', '-', $lote->getNumero()) ?: $id);

        return new Response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
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
