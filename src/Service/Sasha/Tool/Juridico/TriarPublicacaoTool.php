<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoPublicacaoRepository;
use App\Service\Juridico\JuridicoPublicacaoPipelineService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TriarPublicacaoTool implements SashaToolInterface
{
    use ConfirmableToolTrait;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoPublicacaoRepository $pubRepo,
        private JuridicoPublicacaoPipelineService $pipeline,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string { return 'triar_publicacao'; }
    public function getDescription(): string { return 'Executa a triagem IA e o pipeline (prazo/alerta) de uma publicação DJEN'; }
    public function getRequiredScopes(): array { return []; }
    public function supports(User $user): bool { return $this->organismoCopy->isJuridicoProfile(); }

    public function execute(User $user, array $params): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return ['summary' => 'Nenhum escritório ativo.', 'results' => []];
        }
        $id = (int) ($params['publicacao_id'] ?? 0);
        $pub = $id > 0 ? $this->pubRepo->findOneByEmpresa($empresa, $id) : null;
        if ($pub === null) {
            return ['summary' => 'Não encontrei essa publicação. Informe o ID da fila DJEN.', 'results' => []];
        }
        if (!$this->confirmado($params)) {
            return ['summary' => 'Posso triar e abrir prazo automático desta publicação.', 'results' => [
                $this->pedirConfirmacao('triar_publicacao', ['publicacao_id' => $id], 'Triar publicação', [
                    ['label' => 'Publicação', 'value' => $pub->tituloCurto()],
                    ['label' => 'Processo', 'value' => $pub->getNumeroProcesso() ?? '—'],
                ], 'Sim, triar agora'),
            ]];
        }
        $this->pipeline->executar($pub);

        return [
            'summary' => sprintf('Triagem concluída (%s). Pipeline: %s.', $pub->getIaClassificacao() ?? 'ok', $pub->getPipelineStatus()),
            'results' => [['label' => 'Abrir publicações', 'url' => $this->router->generate('app_juridico_publicacoes')]],
        ];
    }
}
