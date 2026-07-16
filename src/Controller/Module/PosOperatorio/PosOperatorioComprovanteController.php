<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\PosOperatorio\ClinicProductCatalog;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\ClinicCarteirinhaService;
use App\Service\PosOperatorio\ClinicComprovanteService;
use App\Service\PosOperatorio\ClinicProductConfigService;
use App\Service\PosOperatorio\PosOperatorioPacienteService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        private ClinicCarteirinhaService $carteirinha,
        private PosOperatorioPacienteService $pacientes,
        private PosOperatorioPacienteRepository $pacienteRepo,
        private ClinicProductConfigService $productConfig,
    ) {}

    #[Route('', name: 'app_pos_operatorio_comprovante')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::COMPROVANTE);

        $painel = $request->query->getString('painel', '');
        if (!\in_array($painel, ['', 'emitidos', 'pendentes'], true)) {
            $painel = '';
        }
        $q = trim($request->query->getString('q'));
        $allEmitidos = $this->comprovante->listComEmissao($empresa);
        $allPacientes = $this->pacienteRepo->findRecentByEmpresa($empresa, 100, 0);
        $emitidos = array_values(array_filter(
            $allEmitidos,
            static fn (PosOperatorioPaciente $p): bool => self::matchesPacienteSearch($p, $q),
        ));
        $pacientes = array_values(array_filter(
            $allPacientes,
            static function (PosOperatorioPaciente $p) use ($q, $painel): bool {
                if ($painel === 'pendentes' && $p->hasComprovanteAtivo()) {
                    return false;
                }
                if (!self::matchesPacienteSearch($p, $q)) {
                    return false;
                }

                return true;
            },
        ));
        $semEmissao = \count(array_filter(
            $allPacientes,
            static fn (PosOperatorioPaciente $p): bool => !$p->hasComprovanteAtivo(),
        ));

        return $this->render(self::T . 'index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'comprovante',
            'emitidos' => $emitidos,
            'pacientes' => $pacientes,
            'filter_painel' => $painel,
            'filter_q' => $q,
            'comprovante_counts' => [
                'emitidos' => \count($allEmitidos),
                'pendentes' => $semEmissao,
                'total' => \count($allPacientes),
            ],
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
            'card' => $this->comprovante->buildCardData($paciente, $empresa),
            'theme' => 'profissional',
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

    #[Route('/paciente/{id}/foto', name: 'app_pos_operatorio_comprovante_foto', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function foto(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->assertProductEnabled($empresa, ClinicProductCatalog::COMPROVANTE);
        $paciente = $this->pacientes->findForEmpresa($empresa, $id);
        if ($paciente === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('comprovante_foto_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessão expirada.');

            return $this->redirectToRoute('app_pos_operatorio_comprovante_paciente', ['id' => $id]);
        }

        $file = $request->files->get('foto');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Selecione uma foto.');

            return $this->redirectToRoute('app_pos_operatorio_comprovante_paciente', ['id' => $id]);
        }

        try {
            $this->carteirinha->storeFoto($paciente, $file);
            $this->addFlash('success', 'Foto atualizada. Reemitir o comprovante para atualizar o documento.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_comprovante_paciente', ['id' => $id]);
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

    private static function matchesPacienteSearch(PosOperatorioPaciente $paciente, string $q): bool
    {
        if ($q === '') {
            return true;
        }
        $haystack = mb_strtolower(implode(' ', array_filter([
            $paciente->getNome(),
            $paciente->getCodigo(),
            $paciente->getProcedimento(),
        ])));

        return str_contains($haystack, mb_strtolower($q));
    }
}
