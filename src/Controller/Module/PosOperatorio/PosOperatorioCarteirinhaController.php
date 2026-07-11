<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\ClinicCarteirinhaService;
use App\Service\PosOperatorio\ClinicProductConfigService;
use App\Service\PosOperatorio\PosOperatorioPacienteService;
use App\Service\WorkspaceService;
use App\PosOperatorio\ClinicProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/carteirinha')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioCarteirinhaController extends AbstractController
{
    private const T = 'modules/pos-operatorio/carteirinha/';

    public function __construct(
        private WorkspaceService $workspace,
        private ClinicCarteirinhaService $carteirinha,
        private PosOperatorioPacienteService $pacientes,
        private PosOperatorioPacienteRepository $pacienteRepo,
        private ClinicProductConfigService $productConfig,
    ) {}

    #[Route('', name: 'app_pos_operatorio_carteirinha')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::CARTEIRINHA);

        return $this->render(self::T . 'index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'carteirinha',
            'emitidos' => $this->carteirinha->listComEmissao($empresa),
            'pacientes' => $this->pacienteRepo->findRecentByEmpresa($empresa, 50, 0),
            'planos' => ClinicCarteirinhaService::PLANOS,
        ]);
    }

    #[Route('/paciente/{id}', name: 'app_pos_operatorio_carteirinha_paciente', requirements: ['id' => '\d+'])]
    public function paciente(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::CARTEIRINHA);
        $paciente = $this->pacientes->findForEmpresa($empresa, $id);
        if ($paciente === null) {
            throw $this->createNotFoundException();
        }

        return $this->render(self::T . 'paciente.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'carteirinha',
            'paciente' => $paciente,
            'card' => $this->carteirinha->buildCardData($paciente, $empresa),
            'theme' => $paciente->getCarteirinhaPlano() ?? 'essencial',
            'planos' => ClinicCarteirinhaService::PLANOS,
            'tem_emissao' => $paciente->hasCarteirinhaAtiva(),
        ]);
    }

    #[Route('/paciente/{id}/emitir', name: 'app_pos_operatorio_carteirinha_emitir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function emitir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::CARTEIRINHA);
        $paciente = $this->pacientes->findForEmpresa($empresa, $id);
        if ($paciente === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('carteirinha_emitir_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessão expirada.');

            return $this->redirectToRoute('app_pos_operatorio_carteirinha_paciente', ['id' => $id]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $plano = (string) $request->request->get('plano', 'essencial');
        $validade = max(7, min(90, (int) $request->request->get('validade_dias', 14)));

        try {
            $this->carteirinha->emitir($paciente, $user, $plano, $validade);
            $this->addFlash('success', 'Carteirinha emitida para ' . $paciente->getNome() . '.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_carteirinha_paciente', ['id' => $id]);
    }

    #[Route('/paciente/{id}/foto', name: 'app_pos_operatorio_carteirinha_foto', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function foto(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::CARTEIRINHA);
        $paciente = $this->pacientes->findForEmpresa($empresa, $id);
        if ($paciente === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('carteirinha_foto_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessão expirada.');

            return $this->redirectToRoute('app_pos_operatorio_carteirinha_paciente', ['id' => $id]);
        }

        $file = $request->files->get('foto');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Selecione uma foto.');

            return $this->redirectToRoute('app_pos_operatorio_carteirinha_paciente', ['id' => $id]);
        }

        try {
            $this->carteirinha->storeFoto($paciente, $file);
            $this->addFlash('success', 'Foto atualizada.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_carteirinha_paciente', ['id' => $id]);
    }

    #[Route('/paciente/{id}/revogar', name: 'app_pos_operatorio_carteirinha_revogar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function revogar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $paciente = $this->pacientes->findForEmpresa($empresa, $id);
        if ($paciente === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('carteirinha_revogar_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessão expirada.');

            return $this->redirectToRoute('app_pos_operatorio_carteirinha_paciente', ['id' => $id]);
        }

        $this->carteirinha->revogar($paciente);
        $this->addFlash('success', 'Carteirinha revogada.');

        return $this->redirectToRoute('app_pos_operatorio_carteirinha_paciente', ['id' => $id]);
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
