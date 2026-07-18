<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicAssinaturaDocumento;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ClinicAssinaturaDocumentoRepository;
use App\Service\Clinic\ClinicAssinaturaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/assinaturas')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioAssinaturaController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicAssinaturaService $assinaturas,
        private ClinicAssinaturaDocumentoRepository $documentos,
    ) {}

    #[Route('', name: 'app_pos_operatorio_assinaturas', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_assinatura_nova', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');
            } else {
                try {
                    $this->assinaturas->create(
                        $empresa,
                        (string) $request->request->get('titulo', ''),
                        (string) $request->request->get('tipo', ClinicAssinaturaDocumento::TIPO_CONSENTIMENTO),
                    );
                    $this->addFlash('success', 'Documento adicionado à fila de assinatura.');
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }

            return $this->redirectToRoute('app_pos_operatorio_assinaturas', [
                'aba' => $request->request->getString('aba', ''),
            ]);
        }

        $aba = $request->query->getString('aba', 'pendentes');
        $statusFilter = match ($aba) {
            'medico' => ClinicAssinaturaDocumento::STATUS_PENDENTE_MEDICO,
            'paciente' => ClinicAssinaturaDocumento::STATUS_PENDENTE_PACIENTE,
            'fila' => ClinicAssinaturaDocumento::STATUS_NA_FILA,
            'concluidas' => 'concluidas',
            default => null,
        };

        $summary = $this->assinaturas->dashboardSummary($empresa, 100);
        $items = array_map(
            fn (ClinicAssinaturaDocumento $d) => [
                'id' => $d->getId(),
                'titulo' => $d->getTitulo(),
                'tipo' => ClinicAssinaturaService::tipoLabels()[$d->getTipo()] ?? $d->getTipo(),
                'status' => $d->getStatus(),
                'status_label' => ClinicAssinaturaService::statusLabels()[$d->getStatus()] ?? $d->getStatus(),
                'paciente' => $d->getPaciente()?->getNome(),
                'criado_em' => $d->getCriadoEm()->format('d/m/Y H:i'),
            ],
            $this->assinaturas->listForEmpresa($empresa, $statusFilter),
        );

        return $this->render('modules/pos-operatorio/assinaturas/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'assinaturas',
            'items' => $items,
            'aba' => $aba,
            'counts' => $summary['counts'],
            'open' => $summary['open'],
            'tipos' => ClinicAssinaturaService::tipoLabels(),
            'status_labels' => ClinicAssinaturaService::statusLabels(),
        ]);
    }

    #[Route('/{id}/avancar', name: 'app_pos_operatorio_assinatura_avancar', methods: ['POST'])]
    public function avancar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $doc = $this->findOwned($empresa, $id);

        if (!$this->isCsrfTokenValid('clinic_assinatura_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');
        } else {
            $this->assinaturas->advanceStatus($doc);
            $this->addFlash('success', 'Status atualizado.');
        }

        return $this->redirectToRoute('app_pos_operatorio_assinaturas', [
            'aba' => $request->request->getString('aba', 'pendentes'),
        ]);
    }

    private function findOwned(Empresa $empresa, int $id): ClinicAssinaturaDocumento
    {
        $doc = $this->documentos->find($id);
        if (!$doc instanceof ClinicAssinaturaDocumento
            || (int) $doc->getEmpresa()->getId() !== (int) $empresa->getId()
        ) {
            throw $this->createNotFoundException();
        }

        return $doc;
    }

    private function requireEmpresa(): Empresa
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Empresa não encontrada.');
        }

        return $empresa;
    }
}
