<?php

namespace App\Service\Vitoria\Tool\Juridico;

use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Service\Juridico\JuridicoJurisprudenciaService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Vitoria\VitoriaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ferramenta híbrida: aciona a IA (JurisFlow) para pesquisar jurisprudência e já
 * registra a consulta no histórico do escritório — igual ao módulo "Jurisprudência IA",
 * só que disparável direto do chat da Bruna com um clique.
 */
final class PesquisarJurisprudenciaTool implements VitoriaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoJurisprudenciaService $jurisprudencia,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'pesquisar_jurisprudencia';
    }

    public function getDescription(): string
    {
        return 'Pesquisa jurisprudência com IA por tema e tribunal, registrando no histórico do escritório';
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
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return ['summary' => 'Nenhum escritório ativo.', 'results' => []];
        }

        $tema = trim((string) ($params['tema'] ?? ''));
        if ($tema === '') {
            return ['summary' => 'Informe o tema que devo pesquisar.', 'results' => []];
        }

        $tribunal = trim((string) ($params['tribunal'] ?? 'Todos')) ?: 'Todos';

        try {
            $resultado = $this->jurisprudencia->buscarComIA($empresa, $tema, $tribunal, '', 'Geral', $user);
        } catch (JuridicoProcessException $e) {
            return [
                'summary' => $e->getMessage(),
                'results' => [[
                    'label' => 'Abrir Jurisprudência IA',
                    'url' => $this->router->generate('app_juridico_jurisprudencia'),
                ]],
            ];
        }

        $results = [];
        foreach (\array_slice($resultado['resultados'], 0, 3) as $item) {
            $preview = array_values(array_filter([
                ['label' => 'Tribunal', 'value' => (string) ($item['tribunal'] ?? '')],
                ['label' => 'Tema', 'value' => (string) ($item['tema'] ?? $tema)],
                !empty($item['resultado']) ? ['label' => 'Resultado', 'value' => (string) $item['resultado']] : null,
                !empty($item['resumo']) ? ['label' => 'Resumo', 'value' => mb_strimwidth((string) $item['resumo'], 0, 160, '…')] : null,
            ]));

            $results[] = [
                'type' => 'confirm',
                'tool' => 'salvar_jurisprudencia',
                'title' => sprintf('%s — %s', $item['tribunal'] ?? '', $item['tema'] ?? $tema),
                'preview' => $preview,
                'confirm_label' => 'Salvar na biblioteca',
                'cancel_label' => 'Agora não',
                'params' => [
                    'tribunal' => $item['tribunal'] ?? '',
                    'tema' => $item['tema'] ?? $tema,
                    'resultado' => $item['resultado'] ?? null,
                    'relevancia' => $item['relevancia'] ?? null,
                    'referencia' => $item['referencia'] ?? null,
                    'resumo' => $item['resumo'] ?? null,
                    'confirmado' => true,
                ],
            ];
        }
        $results[] = [
            'label' => 'Ver na biblioteca de Jurisprudência IA',
            'url' => $this->router->generate('app_juridico_jurisprudencia'),
        ];

        $count = \count($resultado['resultados']);
        $summary = sprintf('Encontrei %d resultado(s) sobre "%s"%s.', $count, $tema, $tribunal !== 'Todos' ? ' no ' . $tribunal : '');

        return ['summary' => $summary, 'results' => $results];
    }
}
