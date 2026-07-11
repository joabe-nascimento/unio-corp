<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\PosOperatorio\ClinicFeatureCatalog;
use App\PosOperatorio\ClinicProtocolLibrary;
use App\Service\PosOperatorio\ClinicIntegrationConfigService;
use App\Service\PosOperatorio\ClinicOperationsService;
use App\Service\PosOperatorio\ClinicReportExportService;
use App\Service\PosOperatorio\PosOperatorioProtocoloService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioOperationsController extends AbstractController
{
    private const T = 'modules/pos-operatorio/ops/';

    public function __construct(
        private WorkspaceService $workspace,
        private ClinicOperationsService $operations,
        private PosOperatorioProtocoloService $protocolos,
        private ClinicReportExportService $exports,
        private ClinicIntegrationConfigService $integrationConfig,
    ) {}

    #[Route('/trabalho', name: 'app_pos_operatorio_trabalho')]
    public function trabalho(): Response
    {
        $empresa = $this->requireEmpresa();
        $queue = $this->operations->buildWorkQueue($empresa);

        return $this->render(self::T . 'trabalho.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'trabalho',
            'queue' => $queue,
        ]);
    }

    #[Route('/qualidade', name: 'app_pos_operatorio_qualidade')]
    public function qualidade(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'qualidade.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'qualidade',
            'quality' => $this->operations->buildQuality($empresa),
        ]);
    }

    #[Route('/retornos', name: 'app_pos_operatorio_retornos')]
    public function retornos(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'retornos.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'retornos',
            'retornos' => $this->operations->buildReturns($empresa),
        ]);
    }

    #[Route('/biblioteca', name: 'app_pos_operatorio_biblioteca')]
    public function biblioteca(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'biblioteca.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'biblioteca',
            'templates' => ClinicProtocolLibrary::templates(),
            'protocolos' => $this->protocolos->listByEmpresa($empresa),
        ]);
    }

    #[Route('/biblioteca/importar/{slug}', name: 'app_pos_operatorio_biblioteca_importar', methods: ['POST'])]
    public function bibliotecaImportar(string $slug, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        if (!$this->isCsrfTokenValid('clinic_biblioteca', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');
            return $this->redirectToRoute('app_pos_operatorio_biblioteca');
        }

        $template = ClinicProtocolLibrary::find($slug);
        if ($template === null) {
            $this->addFlash('error', 'Modelo não encontrado.');
            return $this->redirectToRoute('app_pos_operatorio_biblioteca');
        }

        $existing = $this->protocolos->findMatchingTemplate($empresa, $template);
        if ($existing instanceof \App\Entity\PosOperatorioProtocolo) {
            $this->addFlash('info', sprintf('Protocolo "%s" já estava cadastrado.', $existing->getNome()));

            return $this->redirectToRoute('app_pos_operatorio_protocolo_editar', ['id' => $existing->getId()]);
        }

        $protocolo = $this->protocolos->importFromTemplate($empresa, $template);
        $this->addFlash('success', sprintf('Protocolo "%s" importado da biblioteca.', $protocolo->getNome()));

        return $this->redirectToRoute('app_pos_operatorio_protocolo_editar', ['id' => $protocolo->getId()]);
    }

    #[Route('/lembretes', name: 'app_pos_operatorio_lembretes')]
    public function lembretes(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'lembretes.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'lembretes',
            'reminders' => $this->operations->buildReminders($empresa),
        ]);
    }

    #[Route('/plantao', name: 'app_pos_operatorio_plantao')]
    public function plantao(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'plantao.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'plantao',
            'plantao' => $this->operations->buildPlantao($empresa),
        ]);
    }

    #[Route('/relatorios', name: 'app_pos_operatorio_relatorios')]
    public function relatorios(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'relatorios.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'relatorios',
            'reports' => $this->operations->buildReports($empresa),
        ]);
    }

    #[Route('/relatorios/export/questionarios', name: 'app_pos_operatorio_relatorios_export_questionarios', methods: ['GET'])]
    public function exportQuestionarios(Request $request): StreamedResponse
    {
        $empresa = $this->requireEmpresa();
        $days = max(7, min(365, (int) $request->query->get('days', 90)));

        return $this->exports->exportQuestionarios($empresa, $days);
    }

    #[Route('/relatorios/export/alertas', name: 'app_pos_operatorio_relatorios_export_alertas', methods: ['GET'])]
    public function exportAlertas(): StreamedResponse
    {
        return $this->exports->exportAlertas($this->requireEmpresa());
    }

    #[Route('/relatorios/export/auditoria', name: 'app_pos_operatorio_relatorios_export_auditoria', methods: ['GET'])]
    public function exportAuditoria(): StreamedResponse
    {
        return $this->exports->exportAuditoria($this->requireEmpresa());
    }

    #[Route('/integracoes', name: 'app_pos_operatorio_integracoes')]
    public function integracoes(): Response
    {
        $empresa = $this->requireEmpresa();
        $config = $this->integrationConfig->get($empresa);
        $integrations = ClinicFeatureCatalog::integrations();
        foreach ($integrations as &$int) {
            if (($int['id'] ?? '') === 'webhook') {
                $int['status'] = $this->integrationConfig->webhookConfigured($empresa) ? 'active' : 'configurable';
            }
        }
        unset($int);

        return $this->render(self::T . 'integracoes.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'integracoes',
            'integrations' => $integrations,
            'integration_config' => $config,
        ]);
    }

    #[Route('/integracoes/webhook', name: 'app_pos_operatorio_integracoes_webhook', methods: ['POST'])]
    public function integracoesWebhook(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        if (!$this->isCsrfTokenValid('clinic_webhook', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_integracoes');
        }

        $url = trim((string) $request->request->get('webhook_url', ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->addFlash('error', 'URL do webhook inválida.');

            return $this->redirectToRoute('app_pos_operatorio_integracoes');
        }

        $this->integrationConfig->save($empresa, [
            'webhook_url' => $url,
            'lembretes_sms' => $request->request->has('lembretes_sms'),
        ]);

        $this->addFlash('success', $url === '' ? 'Webhook removido.' : 'Webhook configurado com sucesso.');

        return $this->redirectToRoute('app_pos_operatorio_integracoes');
    }

    #[Route('/compliance', name: 'app_pos_operatorio_compliance')]
    public function compliance(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'compliance.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'compliance',
            'compliance' => $this->operations->buildCompliance($empresa),
        ]);
    }

    #[Route('/config', name: 'app_pos_operatorio_config')]
    public function config(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'config.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'config',
            'config' => $this->operations->buildConfig(),
        ]);
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
