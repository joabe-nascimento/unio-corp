<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicAgendaSolicitacao;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ClinicAgendaSolicitacaoRepository;
use App\Service\Clinic\ClinicPublicBookingService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/solicitacoes')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioAgendaSolicitacaoController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicPublicBookingService $booking,
        private ClinicAgendaSolicitacaoRepository $solicitacoes,
    ) {}

    #[Route('', name: 'app_pos_operatorio_solicitacoes', methods: ['GET'])]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render('modules/pos-operatorio/agenda/solicitacoes.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'solicitacoes',
            'items' => $this->booking->listPendingRows($empresa, 100),
            'booking_url' => $this->booking->publicUrl($empresa),
        ]);
    }

    #[Route('/{id}/recusar', name: 'app_pos_operatorio_solicitacoes_recusar', methods: ['POST'])]
    public function recusar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $solicitacao = $this->findOwned($empresa, $id);

        if (!$this->isCsrfTokenValid('clinic_solicitacao_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_solicitacoes');
        }

        $this->booking->markRejected($solicitacao);
        $this->addFlash('success', 'Solicitação recusada.');

        return $this->redirectToRoute('app_pos_operatorio_solicitacoes');
    }

    #[Route('/{id}/agendar', name: 'app_pos_operatorio_solicitacoes_agendar', methods: ['POST'])]
    public function agendar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $solicitacao = $this->findOwned($empresa, $id);

        if (!$this->isCsrfTokenValid('clinic_solicitacao_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_solicitacoes');
        }

        $this->booking->markScheduled($solicitacao);

        return $this->redirectToRoute('app_pos_operatorio_agenda_novo', [
            'titulo' => ClinicPublicBookingService::motivoLabels()[$solicitacao->getMotivo()] ?? 'Consulta',
            'sugestao_data' => $solicitacao->getDataPreferida()?->format('Y-m-d'),
            'observacao' => sprintf(
                'Solicitação online — %s · %s%s',
                $solicitacao->getNome(),
                $solicitacao->getTelefone(),
                $solicitacao->getObservacao() ? "\n".$solicitacao->getObservacao() : '',
            ),
        ]);
    }

    private function findOwned(Empresa $empresa, int $id): ClinicAgendaSolicitacao
    {
        $solicitacao = $this->solicitacoes->find($id);
        if (!$solicitacao instanceof ClinicAgendaSolicitacao
            || (int) $solicitacao->getEmpresa()->getId() !== (int) $empresa->getId()
        ) {
            throw $this->createNotFoundException();
        }

        return $solicitacao;
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
