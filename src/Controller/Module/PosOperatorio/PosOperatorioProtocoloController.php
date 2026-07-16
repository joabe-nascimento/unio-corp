<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\Service\PosOperatorio\PosOperatorioProtocoloDefaults;
use App\Service\PosOperatorio\PosOperatorioProtocoloService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/protocolos')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioProtocoloController extends AbstractController
{
    private const T = 'modules/pos-operatorio/protocolos/';

    public function __construct(
        private WorkspaceService $workspace,
        private PosOperatorioProtocoloService $service,
    ) {}

    #[Route('', name: 'app_pos_operatorio_protocolos')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'protocolos',
            'protocolos' => $this->service->listByEmpresa($empresa),
        ]);
    }

    #[Route('/novo', name: 'app_pos_operatorio_protocolo_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $empresa, null);
        }

        return $this->render(self::T . 'form.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'protocolos',
            'protocolo' => null,
            'checklist_text' => implode("\n", array_map(
                static fn (array $i) => ($i['dia'] ?? '') . ': ' . ($i['item'] ?? ''),
                PosOperatorioProtocoloDefaults::checklistBasico(),
            )),
            'perguntas_text' => $this->service->formatPerguntasText(PosOperatorioProtocoloDefaults::perguntas()),
            'regras_dor_p1' => PosOperatorioProtocoloDefaults::regrasAlerta()['dor_p1_min'],
            'regras_febre_p2' => PosOperatorioProtocoloDefaults::regrasAlerta()['febre_p2_min'],
        ]);
    }

    #[Route('/{id}/editar', name: 'app_pos_operatorio_protocolo_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $protocolo = $this->service->findForEmpresa($empresa, $id);
        if (!$protocolo) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $empresa, $protocolo);
        }

        return $this->render(self::T . 'form.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'protocolos',
            'protocolo' => $protocolo,
            'checklist_text' => $this->service->formatChecklistText($protocolo),
            'perguntas_text' => $this->service->formatPerguntasText($protocolo),
            'regras_dor_p1' => $protocolo->getRegrasAlerta()['dor_p1_min'] ?? 8,
            'regras_febre_p2' => $protocolo->getRegrasAlerta()['febre_p2_min'] ?? 38.5,
        ]);
    }

    private function handleSave(Request $request, \App\Entity\Empresa $empresa, ?\App\Entity\PosOperatorioProtocolo $protocolo): Response
    {
        if (!$this->isCsrfTokenValid('pos_op_protocolo', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $protocolo
                ? $this->redirectToRoute('app_pos_operatorio_protocolo_editar', ['id' => $protocolo->getId()])
                : $this->redirectToRoute('app_pos_operatorio_protocolo_novo');
        }

        $nome = trim((string) $request->request->get('nome', ''));
        if ($nome === '') {
            $this->addFlash('error', 'Nome é obrigatório.');

            return $protocolo
                ? $this->redirectToRoute('app_pos_operatorio_protocolo_editar', ['id' => $protocolo->getId()])
                : $this->redirectToRoute('app_pos_operatorio_protocolo_novo');
        }

        $data = $request->request->all();
        $data['ativo'] = $request->request->getBoolean('ativo', true);

        try {
            if ($protocolo) {
                $this->service->update($protocolo, $data);
                $this->addFlash('success', 'Protocolo atualizado.');
            } else {
                $this->service->create($empresa, $data);
                $this->addFlash('success', 'Protocolo criado.');
            }
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $protocolo
                ? $this->redirectToRoute('app_pos_operatorio_protocolo_editar', ['id' => $protocolo->getId()])
                : $this->redirectToRoute('app_pos_operatorio_protocolo_novo');
        }

        return $this->redirectToRoute('app_pos_operatorio_protocolos');
    }

    private function requireEmpresa(): \App\Entity\Empresa
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
