<?php

namespace App\Service\Vitoria\Tool\Juridico;

use App\Entity\JuridicoProcesso;
use App\Entity\User;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Juridico\JuridicoRiscoAlertaService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Vitoria\VitoriaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ferramenta autônoma: lê a carteira real do escritório (via motor de alertas de risco
 * já usado no painel de Processos) e devolve um raio-x priorizado — sem IA generativa,
 * direto do banco de dados da empresa ativa.
 */
final class AnalisarCarteiraTool implements VitoriaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoRiscoAlertaService $riscoAlerta,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'analisar_carteira';
    }

    public function getDescription(): string
    {
        return 'Analisa a saúde da carteira de processos e prioriza o que precisa de atenção';
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

        $total = $this->processoRepo->countByEmpresa($empresa);
        $criticos = $this->processoRepo->countByEmpresaAndStatus($empresa, JuridicoProcesso::STATUS_CRITICO);
        $valorCarteira = $this->processoRepo->sumValorAtivoByEmpresa($empresa);
        $taxaExito = $this->processoRepo->taxaExito($empresa);

        $alertas = $this->riscoAlerta->gerarAlertas($empresa);
        $altos = array_values(array_filter($alertas, static fn (array $a) => $a['nivel'] === 'alto'));

        if ($total === 0) {
            return ['summary' => 'A carteira ainda não tem processos cadastrados.', 'results' => []];
        }

        $summaryPartes = [
            sprintf('%d processo(s) na carteira', $total),
            sprintf('%d crítico(s)', $criticos),
            sprintf('R$ %s em valor ativo', number_format($valorCarteira, 2, ',', '.')),
        ];
        if ($taxaExito !== null) {
            $summaryPartes[] = sprintf('%s%% de taxa de êxito', number_format($taxaExito, 1, ',', '.'));
        }
        $summaryPartes[] = $altos === []
            ? 'sem alertas de alta prioridade agora'
            : sprintf('%d alerta(s) de alta prioridade pedindo atenção imediata', \count($altos));

        $summary = ucfirst(implode(', ', $summaryPartes)) . '.';

        $results = [];
        foreach (\array_slice($altos, 0, 5) as $alerta) {
            $processo = $alerta['processo'];
            $results[] = [
                'label' => sprintf('%s — %s', $processo->getNumero(), $alerta['mensagem']),
                'url' => $this->router->generate('app_juridico_processo_show', ['id' => $processo->getId()]),
            ];
        }

        if ($results === []) {
            $results[] = [
                'label' => 'Ver todos os processos',
                'url' => $this->router->generate('app_juridico_processos'),
            ];
        }

        return ['summary' => $summary, 'results' => $results];
    }
}
