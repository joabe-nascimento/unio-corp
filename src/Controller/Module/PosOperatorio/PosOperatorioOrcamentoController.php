<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicCadastroRules;
use App\Service\PosOperatorio\ClinicOrcamentoService;
use App\Service\PosOperatorio\PosOperatorioPacienteService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/orcamentos')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioOrcamentoController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicOrcamentoService $orcamentos,
        private PosOperatorioPacienteService $pacientes,
    ) {}

    #[Route('', name: 'app_pos_operatorio_orcamentos', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_orcamento_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_orcamentos');
            }

            try {
                $this->orcamentos->create($empresa, $this->payload($request));
                $this->addFlash('success', 'Orçamento cadastrado.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_orcamentos');
        }

        $lista = $this->orcamentos->list($empresa);
        $statusOptions = [];
        foreach ($lista as $orcamento) {
            $statusOptions[$orcamento->getId()] = $this->orcamentos->selectableStatuses($orcamento->getStatus());
        }

        return $this->render('modules/pos-operatorio/orcamentos/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'orcamentos',
            'orcamentos' => $lista,
            'statuses' => ClinicCadastroRules::ORCAMENTO_STATUSES,
            'status_options' => $statusOptions,
            'pacientes' => $this->pacientes->listByEmpresa($empresa, 200),
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_orcamentos_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $orcamento = $this->orcamentos->findForEmpresa($empresa, $id);
        if ($orcamento === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_orcamento_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_orcamentos');
        }

        try {
            $this->orcamentos->update($orcamento, $empresa, $this->payload($request));
            $this->addFlash('success', 'Orçamento atualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_orcamentos');
    }

    #[Route('/{id}/converter', name: 'app_pos_operatorio_orcamentos_converter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function converter(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $orcamento = $this->orcamentos->findForEmpresa($empresa, $id);
        if ($orcamento === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_orcamento_convert_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_orcamentos');
        }

        $pacienteId = $request->request->getInt('paciente_id');
        $paciente = $this->pacientes->findForEmpresa($empresa, $pacienteId);
        if ($paciente === null) {
            $this->addFlash('error', 'Paciente não encontrado.');

            return $this->redirectToRoute('app_pos_operatorio_orcamentos');
        }

        try {
            $this->orcamentos->convertToPacienteLink($orcamento, $paciente);
            $this->addFlash('success', 'Orçamento vinculado ao paciente.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_orcamentos');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $itens = [];
        $nomes = $request->request->all('item_nome');
        $valores = $request->request->all('item_valor');
        if (\is_array($nomes) && $nomes !== []) {
            foreach ($nomes as $i => $nome) {
                $itens[] = [
                    'nome' => (string) $nome,
                    'valor' => (string) ($valores[$i] ?? '0'),
                ];
            }
        } else {
            $itens = $request->request->getString('itens');
        }

        return [
            'lead_nome' => $request->request->getString('lead_nome'),
            'lead_telefone' => $request->request->getString('lead_telefone'),
            'lead_email' => $request->request->getString('lead_email'),
            'itens' => $itens,
            'desconto' => $request->request->getString('desconto'),
            'validade' => $request->request->getString('validade'),
            'observacoes' => $request->request->getString('observacoes'),
            'status' => $request->request->getString('status'),
        ];
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
