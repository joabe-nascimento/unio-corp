<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Service\Juridico\JurisFlowAiClient;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;

/**
 * Gera uma minuta (petição, contestação, procuração, contrato, etc.) a partir
 * de uma descrição do usuário, usando a chain `document-generation` do
 * JurisFlow — devolve um rascunho para revisão, nada é salvo automaticamente.
 */
final class GerarMinutaTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JurisFlowAiClient $jurisFlowAi,
    ) {
    }

    public function getName(): string
    {
        return 'gerar_minuta';
    }

    public function getDescription(): string
    {
        return 'Gera uma minuta de petição, contestação, procuração ou contrato a partir de uma descrição';
    }

    public function getRequiredScopes(): array
    {
        return [];
    }

    public function supports(User $user): bool
    {
        return $this->organismoCopy->isJuridicoProfile();
    }

    public function execute(User $user, array $params): array
    {
        $descricao = trim((string) ($params['descricao'] ?? $params['dados'] ?? $params['texto'] ?? ''));
        if ($descricao === '') {
            return ['summary' => 'Para gerar uma minuta, preciso que você descreva:\n• Quem são as partes (autor/réu)\n• Qual o pedido principal\n• Quais os fatos relevantes\n\nExemplo: "autor João Silva contra Empresa XYZ, pedido de indenização por danos morais, fatos: cobrança indevida em 15/03/2026"', 'results' => []];
        }

        // Valida se não é apenas placeholder ou texto muito curto. Anexos de arquivo
        // chegam no formato "[Anexo: nome.pdf]\n<texto>" — o regex só rejeita
        // placeholders de exemplo tipo "[descreva aqui]", nunca um anexo real.
        if (mb_strlen($descricao) < 30 || preg_match('/\[\s*(cole|insira|preencha|descreva)/ui', $descricao) === 1) {
            return ['summary' => 'A descrição está muito curta ou incompleta. Por favor, forneça detalhes sobre:\n• Partes envolvidas\n• Pedido/objetivo do documento\n• Fatos e fundamentos', 'results' => []];
        }

        $tipo = trim((string) ($params['tipo'] ?? 'petição')) ?: 'petição';

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $escritorioId = $empresa?->getId() !== null ? (string) $empresa->getId() : '';

        $minuta = $this->jurisFlowAi->gerarMinuta($tipo, $descricao, $escritorioId);
        if ($minuta === null || trim($minuta) === '') {
            return ['summary' => 'O serviço de IA está temporariamente indisponível. Aguarde alguns instantes e tente novamente.', 'results' => []];
        }

        return ['summary' => $minuta, 'results' => []];
    }
}
