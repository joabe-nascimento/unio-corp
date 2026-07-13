<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\PosOperatorio\ClinicFeatureCatalog;
use App\PosOperatorio\ClinicProtocolLibrary;
use App\Service\PosOperatorio\ClinicAgendaReminderService;
use App\Service\PosOperatorio\ClinicAgendaService;
use App\Service\PosOperatorio\ClinicAltaIntakeService;
use App\Service\PosOperatorio\ClinicDutyRosterService;
use App\Service\PosOperatorio\ClinicIntegrationConfigService;
use App\Service\PosOperatorio\ClinicOperationsService;
use App\Service\PosOperatorio\ClinicPolicyConfigService;
use App\Service\PosOperatorio\ClinicReportExportService;
use App\Service\PosOperatorio\PosOperatorioProtocoloService;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService;
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
        private ClinicAgendaService $agenda,
        private PosOperatorioProtocoloService $protocolos,
        private ClinicReportExportService $exports,
        private ClinicIntegrationConfigService $integrationConfig,
        private ClinicPolicyConfigService $policy,
        private ClinicDutyRosterService $duty,
        private ClinicAltaIntakeService $alta,
        private ClinicAgendaReminderService $agendaReminders,
        private ClinicWhatsappService $whatsapp,
    ) {}

    #[Route('/trabalho', name: 'app_pos_operatorio_trabalho')]
    public function trabalho(): Response
    {
        $empresa = $this->requireEmpresa();
        $queue = $this->operations->buildWorkQueue($empresa);
        $policy = $this->policy->get($empresa);

        return $this->render(self::T . 'trabalho.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'trabalho',
            'queue' => $queue,
            'continuity_lead' => $policy['continuity_lead'],
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
            'retornos' => $this->agenda->buildReturnSuggestions($empresa),
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

    #[Route('/lembretes', name: 'app_pos_operatorio_lembretes', methods: ['GET', 'POST'])]
    public function lembretes(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_agenda_reminders', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_lembretes');
            }

            $result = $this->agendaReminders->prepareForTomorrow($empresa);
            if ($result['enviados'] > 0) {
                $hint = $this->whatsapp->isLive()
                    ? 'WhatsApp Meta + e-mail/webhook'
                    : 'wa.me + e-mail/webhook (Meta ainda não configurado)';
                $this->addFlash(
                    'success',
                    sprintf(
                        '%d lembrete(s) de confirmação processados para amanhã (%s).',
                        $result['enviados'],
                        $hint,
                    ),
                );
            } else {
                $this->addFlash('info', 'Nenhum horário pendente de lembrete para amanhã.');
            }

            return $this->redirectToRoute('app_pos_operatorio_lembretes');
        }

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

    #[Route('/plantao/salvar', name: 'app_pos_operatorio_plantao_salvar', methods: ['POST'])]
    public function plantaoSalvar(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        if (!$this->isCsrfTokenValid('clinic_plantao', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_plantao');
        }

        $ids = $request->request->all('plantao_user_ids');
        if (!\is_array($ids)) {
            $ids = [];
        }
        $this->duty->setOnCall($empresa, array_map('intval', $ids));
        $this->addFlash('success', 'Plantão atualizado. P1 segue essa escala.');

        return $this->redirectToRoute('app_pos_operatorio_plantao');
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
            if (($int['id'] ?? '') === 'whatsapp') {
                $int['status'] = $this->whatsapp->isLive() ? 'active' : 'prepared';
                $int['desc'] = $this->whatsapp->isLive()
                    ? 'Meta Cloud API ativa: confirmação D-1 e questionário com log de entrega.'
                    : 'wa.me + webhook. Configure WHATSAPP_PROVIDER=meta e WHATSAPP_META_* no servidor para envio live.';
            }
        }
        unset($int);

        $altaToken = $this->alta->ensureToken($empresa);

        return $this->render(self::T . 'integracoes.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'integracoes',
            'integrations' => $integrations,
            'integration_config' => $config,
            'alta_token' => $altaToken,
            'alta_endpoint' => $this->generateUrl('app_pos_operatorio_api_alta', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            'whatsapp_live' => $this->whatsapp->isLive(),
            'whatsapp_provider' => $this->whatsapp->providerName(),
        ]);
    }

    #[Route('/integracoes/whatsapp-teste', name: 'app_pos_operatorio_integracoes_whatsapp_teste', methods: ['POST'])]
    public function integracoesWhatsappTeste(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        if (!$this->isCsrfTokenValid('clinic_whatsapp_teste', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_integracoes');
        }

        if (!$this->whatsapp->isLive()) {
            $this->addFlash('error', 'WhatsApp Meta não está configurado no servidor (WHATSAPP_META_*).');

            return $this->redirectToRoute('app_pos_operatorio_integracoes');
        }

        $telefone = trim((string) $request->request->get('telefone', ''));
        $result = $this->whatsapp->send(
            $empresa,
            $telefone,
            'Unio Saúde — mensagem de teste. Se você recebeu isto, a API Meta está ativa.',
            ['event' => 'whatsapp_teste'],
        );

        if ($result->sent) {
            $this->addFlash('success', 'Mensagem de teste enviada via Meta Cloud API.');
        } else {
            $this->addFlash('error', 'Falha no teste WhatsApp: '.($result->error ?? $result->status));
        }

        return $this->redirectToRoute('app_pos_operatorio_integracoes');
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
            'config' => $this->operations->buildConfig($empresa),
        ]);
    }

    #[Route('/config/salvar', name: 'app_pos_operatorio_config_salvar', methods: ['POST'])]
    public function configSalvar(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        if (!$this->isCsrfTokenValid('clinic_config', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_config');
        }

        $current = $this->policy->get($empresa);
        $this->policy->save($empresa, [
            'sla' => [
                'P1' => (int) $request->request->get('sla_p1', $current['sla']['P1']),
                'P2' => (int) $request->request->get('sla_p2', $current['sla']['P2']),
                'P3' => (int) $request->request->get('sla_p3', $current['sla']['P3']),
                'P4' => (int) $request->request->get('sla_p4', $current['sla']['P4']),
            ],
            'triagem' => [
                'dor_p1_min' => (float) $request->request->get('dor_p1_min', $current['triagem']['dor_p1_min']),
                'dor_p2_min' => (float) $request->request->get('dor_p2_min', $current['triagem']['dor_p2_min']),
                'febre_p2_min' => (float) $request->request->get('febre_p2_min', $current['triagem']['febre_p2_min']),
            ],
            'escalacao_horas' => array_filter(array_map('intval', explode(',', (string) $request->request->get('escalacao_horas', '4,8,24')))),
            'canais' => [
                'in_app' => $request->request->has('canal_in_app'),
                'email' => $request->request->has('canal_email'),
                'whatsapp' => $request->request->has('canal_whatsapp'),
                'sms' => $request->request->has('canal_sms'),
            ],
            'retencao_dias' => (int) $request->request->get('retencao_dias', $current['retencao_dias']),
            'alta_token' => $current['alta_token'],
            'continuity_lead' => trim((string) $request->request->get('continuity_lead', $current['continuity_lead'])),
        ]);

        $this->addFlash('success', 'Política de continuidade salva.');

        return $this->redirectToRoute('app_pos_operatorio_config');
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
