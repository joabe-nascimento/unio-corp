<?php

namespace App\Controller\Api;

use App\Service\Clinic\ClinicWhatsappService;
use App\Service\PosOperatorio\Whatsapp\WhatsappTemplateLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API para testes de envio de mensagens WhatsApp
 */
#[Route('/api/whatsapp')]
#[IsGranted('ROLE_TENANT')]
class WhatsappTestController extends AbstractController
{
    public function __construct(
        private ?ClinicWhatsappService $whatsappService = null,
    ) {}

    /**
     * Envia uma mensagem de teste para validar a configuração
     * 
     * POST /api/whatsapp/test
     * Body: {
     *   "phone": "+5511987654321",
     *   "template": "confirmacao",
     *   "params": { ... }
     * }
     */
    #[Route('/test', name: 'api_whatsapp_test', methods: ['POST'])]
    public function sendTest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json([
                'success' => false,
                'error' => 'JSON inválido',
            ], Response::HTTP_BAD_REQUEST);
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        $template = trim((string) ($data['template'] ?? ''));

        if ($phone === '') {
            return $this->json([
                'success' => false,
                'error' => 'Número de telefone obrigatório',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($template === '') {
            return $this->json([
                'success' => false,
                'error' => 'Template obrigatório',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Gerar mensagem baseada no template
        $message = $this->generateTestMessage($template);

        if ($message === null) {
            return $this->json([
                'success' => false,
                'error' => 'Template não encontrado',
                'available_templates' => [
                    'confirmacao',
                    'trilha',
                    'resultado',
                    'lembrete',
                    'cobranca',
                    'pagamento',
                    'pesquisa',
                    'aniversario',
                    'alta',
                    'documentos',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Verificar se o serviço está disponível
            if (!$this->whatsappService) {
                return $this->json([
                    'success' => false,
                    'error' => 'Serviço WhatsApp não configurado. Implemente ClinicWhatsappService primeiro.',
                    'message_preview' => substr($message, 0, 200),
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            // Envia a mensagem via serviço
            $result = $this->whatsappService->enviarMensagem($phone, $message);

            if ($result['success'] ?? false) {
                return $this->json([
                    'success' => true,
                    'message' => 'Mensagem de teste enviada com sucesso',
                    'phone' => $phone,
                    'template' => $template,
                    'message_preview' => substr($message, 0, 100) . '...',
                    'provider_response' => $result['data'] ?? null,
                ]);
            }

            return $this->json([
                'success' => false,
                'error' => $result['error'] ?? 'Erro desconhecido ao enviar mensagem',
                'details' => $result['details'] ?? null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erro ao enviar mensagem: ' . $e->getMessage(),
                'type' => get_class($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Retorna informações sobre os templates disponíveis
     */
    #[Route('/templates', name: 'api_whatsapp_templates', methods: ['GET'])]
    public function listTemplates(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'templates' => [
                [
                    'id' => 'confirmacao',
                    'name' => 'Confirmação de Agendamento',
                    'description' => 'Lembrete D-1 pedindo confirmação de consulta',
                    'preview' => WhatsappTemplateLibrary::agendaConfirmacao(
                        'João da Silva',
                        new \DateTime('+1 day 14:30'),
                        'Consulta de Cardiologia',
                        'Dr. Pedro Cardiologista',
                        'Clínica Centro - Av. Paulista, 1000'
                    ),
                ],
                [
                    'id' => 'trilha',
                    'name' => 'Marco da Trilha',
                    'description' => 'Marcos da jornada pré/pós-operatória',
                    'preview' => WhatsappTemplateLibrary::trilhaMarco(
                        'Maria Santos',
                        -3,
                        'Realizar jejum de 8 horas antes do procedimento'
                    ),
                ],
                [
                    'id' => 'resultado',
                    'name' => 'Resultado de Exame',
                    'description' => 'Notifica que resultado está disponível',
                    'preview' => WhatsappTemplateLibrary::resultadoExame(
                        'Carlos Oliveira',
                        'Hemograma Completo',
                        'https://portal.unio.com.br/exames/123'
                    ),
                ],
                [
                    'id' => 'lembrete',
                    'name' => 'Lembrete de Medicação',
                    'description' => 'Lembra o paciente de tomar medicação',
                    'preview' => WhatsappTemplateLibrary::lembreteMedicacao(
                        'Ana Paula',
                        'Paracetamol 750mg',
                        '1 comprimido a cada 6 horas',
                        '14:00'
                    ),
                ],
                [
                    'id' => 'cobranca',
                    'name' => 'Cobrança de Conta',
                    'description' => 'Lembrete de conta em aberto',
                    'preview' => WhatsappTemplateLibrary::cobrancaConta(
                        'Roberto Silva',
                        350.00,
                        new \DateTime('+5 days'),
                        'https://pagamento.unio.com.br/conta/456'
                    ),
                ],
                [
                    'id' => 'pagamento',
                    'name' => 'Confirmação de Pagamento',
                    'description' => 'Confirma recebimento de pagamento',
                    'preview' => WhatsappTemplateLibrary::confirmacaoPagamento(
                        'Fernanda Costa',
                        250.00,
                        'Cartão de crédito'
                    ),
                ],
                [
                    'id' => 'pesquisa',
                    'name' => 'Pesquisa de Satisfação',
                    'description' => 'Solicita feedback pós-atendimento',
                    'preview' => WhatsappTemplateLibrary::pesquisaSatisfacao(
                        'Lucas Martins',
                        'https://pesquisa.unio.com.br/789'
                    ),
                ],
                [
                    'id' => 'aniversario',
                    'name' => 'Aniversário',
                    'description' => 'Mensagem de felicitações',
                    'preview' => WhatsappTemplateLibrary::aniversario('Patricia Almeida'),
                ],
                [
                    'id' => 'alta',
                    'name' => 'Alta Hospitalar',
                    'description' => 'Orientações pós-alta',
                    'preview' => WhatsappTemplateLibrary::altaHospitalar(
                        'Ricardo Souza',
                        new \DateTime(),
                        "• Repouso relativo por 3 dias\n• Evitar esforços físicos\n• Retornar em caso de febre",
                        '25/07/2026'
                    ),
                ],
                [
                    'id' => 'documentos',
                    'name' => 'Solicitação de Documentos',
                    'description' => 'Pede documentos ao paciente',
                    'preview' => WhatsappTemplateLibrary::solicitacaoDocumentos(
                        'Juliana Lima',
                        ['RG', 'CPF', 'Comprovante de residência', 'Carteirinha do convênio'],
                        'até 20/07/2026'
                    ),
                ],
            ],
        ]);
    }

    /**
     * Verifica o status da integração WhatsApp
     */
    #[Route('/status', name: 'api_whatsapp_status', methods: ['GET'])]
    public function checkStatus(): JsonResponse
    {
        if (!$this->whatsappService) {
            return $this->json([
                'success' => false,
                'status' => 'not_configured',
                'message' => 'Serviço WhatsApp não está configurado',
            ]);
        }

        try {
            $status = $this->whatsappService->checkConnection();

            return $this->json([
                'success' => true,
                'status' => $status['connected'] ?? false ? 'connected' : 'disconnected',
                'provider' => $status['provider'] ?? 'unknown',
                'phone_number' => $status['phone'] ?? null,
                'details' => $status,
            ]);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function generateTestMessage(string $template): ?string
    {
        return match ($template) {
            'confirmacao' => WhatsappTemplateLibrary::agendaConfirmacao(
                'Paciente Teste',
                new \DateTime('+1 day 14:30'),
                'Consulta de Teste',
                'Dr. Teste',
                'Clínica Teste - Endereço Teste'
            ),
            'trilha' => WhatsappTemplateLibrary::trilhaMarco(
                'Paciente Teste',
                -3,
                'Mensagem de teste da trilha pré-operatória'
            ),
            'resultado' => WhatsappTemplateLibrary::resultadoExame(
                'Paciente Teste',
                'Exame de Teste',
                'https://portal.teste.com.br/exame/123'
            ),
            'lembrete' => WhatsappTemplateLibrary::lembreteMedicacao(
                'Paciente Teste',
                'Medicamento Teste 500mg',
                '1 comprimido a cada 8 horas',
                '14:00'
            ),
            'cobranca' => WhatsappTemplateLibrary::cobrancaConta(
                'Paciente Teste',
                100.00,
                new \DateTime('+7 days'),
                'https://pagamento.teste.com.br/123'
            ),
            'pagamento' => WhatsappTemplateLibrary::confirmacaoPagamento(
                'Paciente Teste',
                150.00,
                'PIX'
            ),
            'pesquisa' => WhatsappTemplateLibrary::pesquisaSatisfacao(
                'Paciente Teste',
                'https://pesquisa.teste.com.br/123'
            ),
            'aniversario' => WhatsappTemplateLibrary::aniversario('Paciente Teste'),
            'alta' => WhatsappTemplateLibrary::altaHospitalar(
                'Paciente Teste',
                new \DateTime(),
                "• Orientação 1\n• Orientação 2\n• Orientação 3",
                '30/07/2026'
            ),
            'documentos' => WhatsappTemplateLibrary::solicitacaoDocumentos(
                'Paciente Teste',
                ['RG', 'CPF', 'Carteirinha'],
                'até 25/07/2026'
            ),
            default => null,
        };
    }
}
