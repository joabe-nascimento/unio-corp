<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Organismo\OrganismoCareContract;
use App\Entity\User;
use App\Repository\Organismo\OrganismoCareContractRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\Organismo\Contract\CareContractService;
use App\Service\Organismo\Contract\ContractAttestationService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Organismo\OrganismoFeature;
use App\Service\Organismo\Runtime\OrganRuntime;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/organismo')]
#[IsGranted('ROLE_USER')]
final class OrganismoRuntimeController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private OrganismoFeature $feature,
        private OrganismoCopyService $copy,
        private CareContractService $contracts,
        private ContractAttestationService $attestations,
        private OrganismoCareContractRepository $contractRepo,
        private PosOperatorioPacienteRepository $pacientes,
        private OrganRuntime $runtime,
    ) {
    }

    #[Route('/contratos', name: 'app_organismo_contratos', methods: ['GET'])]
    public function contratos(): Response
    {
        $this->assertClinic();
        $empresa = $this->requireEmpresa();
        $items = [];
        foreach ($this->contracts->listActive($empresa) as $c) {
            $items[] = [
                'id' => $c->getId(),
                'paciente' => $c->getPaciente()->getNome(),
                'codigo' => $c->getPaciente()->getCodigo(),
                'paciente_id' => $c->getPaciente()->getId(),
                'protocolo' => $c->getProtocolo()?->getNome() ?? '—',
                'versao' => $c->getVersao(),
                'hash' => substr($c->getContentHash(), 0, 12),
                'status' => $c->getStatus(),
                'marcos_ok' => $c->getAttestations()->count(),
            ];
        }

        return $this->render('modules/pos-operatorio/organismo/contratos.html.twig', [
            'empresa' => $empresa,
            'contratos' => $items,
            'pos_section' => 'organismo_contratos',
        ]);
    }

    #[Route('/contratos/{id}/atestar', name: 'app_organismo_contrato_atestar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function atestar(int $id, Request $request): Response
    {
        $this->assertClinic();
        $empresa = $this->requireEmpresa();
        $contract = $this->contractRepo->find($id);
        if (!$contract instanceof OrganismoCareContract || $contract->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException();
        }

        $marco = trim((string) $request->request->get('marco_key', ''));
        $evidencia = trim((string) $request->request->get('evidencia', 'Atestação manual'));
        if ($marco === '') {
            $this->addFlash('danger', 'Informe o marco a atestar.');

            return $this->redirectToFicha($contract);
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->attestations->attest($contract, $marco, $evidencia !== '' ? $evidencia : 'Atestação manual', $user);
        $this->addFlash('success', 'Marco atestado na Trilha Unio. Saúde que acompanha.');

        return $this->redirectToFicha($contract);
    }

    #[Route('/bootstrap-contratos', name: 'app_organismo_bootstrap_contratos', methods: ['POST'])]
    public function bootstrapContratos(): Response
    {
        $this->assertClinic();
        $empresa = $this->requireEmpresa();
        $n = 0;
        foreach ($this->pacientes->findRecentByEmpresa($empresa, 100, 0) as $paciente) {
            if ($paciente->getProtocolo() === null) {
                continue;
            }
            if ($this->contracts->ensureForPaciente($paciente) !== null) {
                ++$n;
            }
        }
        $this->addFlash('success', sprintf('%d contrato(s) de cuidado sincronizado(s).', $n));

        return $this->redirectToRoute('app_organismo_contratos');
    }

    #[Route('/tick', name: 'app_organismo_tick', methods: ['POST'])]
    public function tick(): Response
    {
        $this->assertClinic();
        $empresa = $this->requireEmpresa();
        $state = $this->runtime->tick($empresa, true);
        $this->addFlash('success', sprintf(
            'Organismo atualizado — vitalidade %d (%s), %d cenário(s).',
            $state['vitality']['score'],
            $state['vitality']['nivel'],
            \count($state['twin']['scenarios']),
        ));

        return $this->redirectToRoute('app_pulso');
    }

    private function redirectToFicha(OrganismoCareContract $contract): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_pacientes', [
            'open_ficha' => $contract->getPaciente()->getId(),
        ]);
    }

    private function assertClinic(): void
    {
        if (!$this->feature->isEnabled() || !$this->copy->isClinicProfile()) {
            throw $this->createAccessDeniedException('Organismo Runtime disponível apenas na Unio Saúde.');
        }
    }

    private function requireEmpresa(): \App\Entity\Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Selecione uma clínica.');
        }

        return $empresa;
    }
}
