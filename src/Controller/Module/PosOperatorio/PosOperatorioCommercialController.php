<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ClinicDocumentoEmissaoRepository;
use App\Repository\ClinicVerificacaoLogRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\PosOperatorio\ClinicCommercialPlans;
use App\Service\Clinic\ClinicBrandingService;
use App\Service\Clinic\ClinicOnboardingService;
use App\Service\Clinic\ClinicPlanLimitsService;
use App\Service\Clinic\ClinicSandboxService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/comercial')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioCommercialController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicBrandingService $branding,
        private ClinicPlanLimitsService $planLimits,
        private ClinicOnboardingService $onboarding,
        private ClinicSandboxService $sandbox,
        private ClinicDocumentoEmissaoRepository $emissaoRepo,
        private ClinicVerificacaoLogRepository $verificacaoLogRepo,
        private PosOperatorioPacienteRepository $pacientes,
    ) {}

    #[Route('', name: 'app_pos_operatorio_comercial')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render('modules/pos-operatorio/comercial/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'comercial',
            'branding' => $this->branding->get($empresa),
            'limits' => $this->planLimits->usageSummary($empresa),
            'onboarding' => $this->onboarding->status($empresa),
        ]);
    }

    #[Route('/branding', name: 'app_pos_operatorio_comercial_branding', methods: ['GET', 'POST'])]
    public function branding(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            $this->branding->save($empresa, [
                'logo_url' => $request->request->getString('logo_url'),
                'cor_primaria' => $request->request->getString('cor_primaria'),
                'slogan' => $request->request->getString('slogan'),
                'nome_exibicao' => $request->request->getString('nome_exibicao'),
            ]);
            $this->onboarding->patch($empresa, ['branding_configurado' => true]);
            $this->addFlash('success', 'Branding atualizado.');

            return $this->redirectToRoute('app_pos_operatorio_comercial_branding');
        }

        return $this->render('modules/pos-operatorio/comercial/branding.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'comercial',
            'branding' => $this->branding->get($empresa),
        ]);
    }

    #[Route('/limites', name: 'app_pos_operatorio_comercial_limites', methods: ['GET', 'POST'])]
    public function limites(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            $planId = $this->planLimits->normalizePlanId($request->request->getString('plano_comercial'));
            $plan = ClinicCommercialPlans::find($planId);
            $defaultMax = (int) ($plan['max_beneficiarios'] ?? 500);

            $this->planLimits->save($empresa, [
                'plano_comercial' => $planId,
                'max_beneficiarios' => (int) $request->request->get('max_beneficiarios', $defaultMax),
                'wallet_incluso' => $request->request->getBoolean('wallet_incluso'),
            ]);
            $this->addFlash('success', 'Limites do plano salvos.');

            return $this->redirectToRoute('app_pos_operatorio_comercial_limites');
        }

        return $this->render('modules/pos-operatorio/comercial/limites.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'comercial',
            'limits' => $this->planLimits->usageSummary($empresa),
            'commercial_plans' => ClinicCommercialPlans::all(),
        ]);
    }

    #[Route('/onboarding', name: 'app_pos_operatorio_comercial_onboarding', methods: ['GET', 'POST'])]
    public function onboardingPage(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->getString('action');
            if ($action === 'sandbox') {
                $this->sandbox->ensureSandbox($empresa, $user);
                $this->addFlash('success', 'Sandbox Ana / João / Maria atualizado.');
            } elseif ($action === 'csv') {
                $csv = (string) $request->request->get('csv', '');
                $result = $this->onboarding->importCsv($empresa, $csv, $user);
                if ($result['ok']) {
                    $this->addFlash('success', sprintf('Importados %d paciente(s).', $result['importados']));
                } else {
                    $this->addFlash('error', $result['error']);
                }
            } elseif ($action === 'primeira_carteirinha') {
                $paciente = $this->pacientes->findRecentByEmpresa($empresa, 1, 0)[0] ?? null;
                if ($paciente !== null) {
                    $this->onboarding->emitirPrimeiraCarteirinha($empresa, $paciente, $user);
                    $this->addFlash('success', 'Primeira carteirinha emitida.');
                }
            } elseif ($action === 'produtos') {
                $this->onboarding->markProdutosAtivados($empresa);
                $this->addFlash('success', 'Produtos marcados como ativos no onboarding.');
            }

            return $this->redirectToRoute('app_pos_operatorio_comercial_onboarding');
        }

        return $this->render('modules/pos-operatorio/comercial/onboarding.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'comercial',
            'onboarding' => $this->onboarding->status($empresa),
            'sandbox' => $this->pacientes->findSandboxByEmpresa($empresa),
        ]);
    }

    #[Route('/auditoria.csv', name: 'app_pos_operatorio_comercial_auditoria_csv')]
    public function auditoriaCsv(): StreamedResponse
    {
        $empresa = $this->requireEmpresa();

        $response = new StreamedResponse(function () use ($empresa): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['tipo', 'acao', 'codigo', 'paciente', 'plano', 'hash', 'criado_em']);
            foreach ($this->emissaoRepo->findRecentByEmpresa($empresa, 500) as $e) {
                fputcsv($out, [
                    $e->getTipo(),
                    $e->getAcao(),
                    $e->getCodigoVerificacao(),
                    $e->getPaciente()->getNome(),
                    $e->getPlano(),
                    $e->getHashDocumento(),
                    $e->getCriadoEm()->format('Y-m-d H:i:s'),
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['verificacao_log']);
            fputcsv($out, ['codigo', 'status', 'tipo', 'ip', 'origem', 'criado_em']);
            foreach ($this->verificacaoLogRepo->findRecentByEmpresa($empresa, 500) as $l) {
                fputcsv($out, [
                    $l->getCodigo(),
                    $l->getStatus(),
                    $l->getTipo(),
                    $l->getIp(),
                    $l->getOrigem(),
                    $l->getCriadoEm()->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="auditoria-clinica.csv"');

        return $response;
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
