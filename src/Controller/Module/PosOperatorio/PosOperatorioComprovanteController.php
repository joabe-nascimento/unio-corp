<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\PosOperatorio\ClinicProductCatalog;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\ClinicComprovanteService;
use App\Service\PosOperatorio\ClinicProductConfigService;
use App\Service\PosOperatorio\PosOperatorioPacienteService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/comprovante')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioComprovanteController extends AbstractController
{
    private const T = 'modules/pos-operatorio/comprovante/';

    public function __construct(
        private WorkspaceService $workspace,
        private ClinicComprovanteService $comprovante,
        private PosOperatorioPacienteService $pacientes,
        private PosOperatorioPacienteRepository $pacienteRepo,
        private ClinicProductConfigService $productConfig,
    ) {}

    #[Route('', name: 'app_pos_operatorio_comprovante')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::COMPROVANTE);

        return $this->render(self::T . 'index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'comprovante',
            'emitidos' => $this->comprovante->listComEmissao($empresa),
            'pacientes' => $this->pacienteRepo->findRecentByEmpresa($empresa, 50, 0),
        ]);
    }

    #[Route('/paciente/{id}', name: 'app_pos_operatorio_comprovante_paciente', requirements: ['id' => '\d+'])]
    public function paciente(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::COMPROVANTE);
        $paciente = $this->pacientes->findForEmpresa($empresa, $id);
        if ($paciente === null) {
            throw $this->createNotFoundException();
        }

        $verificacaoUrl = null;
        if ($paciente->getComprovanteVerificacao() !== null) {
            $verificacaoUrl = $this->generateUrl(
                'app_verificar_documento',
                ['codigo' => $paciente->getComprovanteVerificacao()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        return $this->render(self::T . 'paciente.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'comprovante',
            'paciente' => $paciente,
            'proof' => $this->comprovante->buildProofData($paciente, $empresa),
            'tem_emissao' => $paciente->hasComprovanteAtivo(),
            'verificacao_url' => $verificacaoUrl,
            'portal_url' => $this->generateUrl('app_comprovante_procedimento', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/paciente/{id}/emitir', name: 'app_pos_operatorio_comprovante_emitir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function emitir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::COMPROVANTE);
        $paciente = $this->pacientes->findForEmpresa($empresa, $id);
        if ($paciente === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('comprovante_emitir_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessão expirada.');

            return $this->redirectToRoute('app_pos_operatorio_comprovante_paciente', ['id' => $id]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $validade = max(7, min(180, (int) $request->request->get('validade_dias', 30)));

        try {
            $this->comprovante->emitir($paciente, $user, $validade);
            $this->addFlash('success', 'Comprovante emitido para ' . $paciente->getNome() . '.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_comprovante_paciente', ['id' => $id, '_fragment' => 'enviar']);
    }

    #[Route('/paciente/{id}/revogar', name: 'app_pos_operatorio_comprovante_revogar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function revogar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $paciente = $this->pacientes->findForEmpresa($empresa, $id);
        if ($paciente === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('comprovante_revogar_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessão expirada.');

            return $this->redirectToRoute('app_pos_operatorio_comprovante_paciente', ['id' => $id]);
        }

        $this->comprovante->revogar($paciente);
        $this->addFlash('success', 'Comprovante revogado.');

        return $this->redirectToRoute('app_pos_operatorio_comprovante_paciente', ['id' => $id]);
    }

    private function requireEmpresa(): Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Selecione uma clínica para continuar.');
        }

        return $empresa;
    }

    private function assertProductEnabled(Empresa $empresa, string $productId): void
    {
        if (!$this->productConfig->isEnabled($empresa, $productId)) {
            throw $this->createAccessDeniedException('Produto desativado. Ative em Produtos da plataforma.');
        }
    }
}
