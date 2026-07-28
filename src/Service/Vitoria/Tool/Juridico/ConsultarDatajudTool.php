<?php

namespace App\Service\Vitoria\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoTribunalConfigRepository;
use App\Service\Juridico\DataJud\DataJudClient;
use App\Service\Juridico\DataJud\DataJudException;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Vitoria\VitoriaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ferramenta autônoma: consulta andamentos oficiais de um processo direto na API
 * Pública do DataJud (CNJ) — base nacional que agrega PJe, e-SAJ, Projudi e demais
 * sistemas dos tribunais. Requer que o escritório tenha configurado sua chave gratuita.
 */
final class ConsultarDatajudTool implements VitoriaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoTribunalConfigRepository $configRepo,
        private DataJudClient $datajud,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'consultar_datajud';
    }

    public function getDescription(): string
    {
        return 'Consulta andamentos oficiais de um processo no DataJud (PJe, e-SAJ, Projudi)';
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

        $numero = trim((string) ($params['numero'] ?? $params['query'] ?? ''));
        if ($numero === '') {
            return ['summary' => 'Informe o número do processo no padrão CNJ para eu consultar no DataJud.', 'results' => []];
        }

        $config = $this->configRepo->findByEmpresa($empresa);
        if ($config === null || !$config->isConfigurado()) {
            return [
                'summary' => 'Este escritório ainda não configurou a chave do DataJud. Cadastre-se gratuitamente e cole a chave na tela de Integração com Tribunais.',
                'results' => [[
                    'label' => 'Configurar integração com tribunais',
                    'url' => $this->router->generate('app_juridico_tribunais'),
                ]],
            ];
        }

        try {
            $resultado = $this->datajud->consultarProcesso($numero, (string) $config->getDatajudApiKey());
        } catch (DataJudException $e) {
            return ['summary' => $e->getMessage(), 'results' => []];
        }

        $ultimoMovimento = $resultado['movimentos'][0] ?? null;
        $summary = sprintf(
            'Processo %s (%s)%s. %s',
            $resultado['numero'],
            $resultado['tribunal'],
            $resultado['orgaoJulgador'] ? ' — ' . $resultado['orgaoJulgador'] : '',
            $ultimoMovimento !== null
                ? sprintf('Última movimentação em %s: %s.', $ultimoMovimento['data'] ?? '—', $ultimoMovimento['nome'])
                : 'Sem movimentações registradas na base do DataJud.',
        );

        return [
            'summary' => $summary,
            'results' => [[
                'label' => 'Ver detalhes e histórico completo',
                'url' => $this->router->generate('app_juridico_tribunais'),
            ]],
        ];
    }
}
