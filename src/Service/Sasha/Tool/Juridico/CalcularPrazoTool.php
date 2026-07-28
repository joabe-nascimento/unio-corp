<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Juridico\PrazoProcessualCalculator;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;

/**
 * Ferramenta autônoma: calcula um prazo processual (CPC) sem depender de IA generativa.
 * Determinística — mesma entrada sempre produz a mesma data, com feriados reais do ano.
 *
 * Se `processo_id` for informado, encadeia com a `criar_tarefa`: sugere já deixar o
 * vencimento anotado como tarefa nesse processo, mas só grava depois de confirmado.
 */
final class CalcularPrazoTool implements SashaToolInterface
{
    use ConfirmableToolTrait;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private PrazoProcessualCalculator $calculator,
        private WorkspaceService $workspace,
        private JuridicoProcessoRepository $processoRepo,
    ) {
    }

    public function getName(): string
    {
        return 'calcular_prazo';
    }

    public function getDescription(): string
    {
        return 'Calcula prazo processual em dias úteis/corridos, com feriados e recesso forense';
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
        $dataBaseRaw = trim((string) ($params['data_base'] ?? ''));
        $dataBase = $dataBaseRaw !== ''
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $dataBaseRaw) ?: null
            : new \DateTimeImmutable('today');

        if ($dataBase === null) {
            return ['summary' => 'Não entendi a data base. Use o formato AAAA-MM-DD.', 'results' => []];
        }

        $dias = max(1, (int) ($params['dias'] ?? 15));
        $tipo = (string) ($params['tipo'] ?? PrazoProcessualCalculator::TIPO_UTIL);
        $dobro = (bool) ($params['dobro'] ?? false);

        $resultado = $this->calculator->calcular($dataBase, $dias, $tipo, $dobro, true);

        $feriadosResumo = array_map(
            static fn (array $f) => $f['data'] . ' — ' . $f['nome'],
            \array_slice($resultado['feriados_no_periodo'], 0, 5),
        );

        $results = [[
            'label' => 'Vencimento: ' . $resultado['data_final']->format('d/m/Y'),
            'data_final' => $resultado['data_final']->format('d/m/Y'),
            'dias_efetivos' => $resultado['dias_efetivos'],
            'tipo' => $resultado['tipo'] === PrazoProcessualCalculator::TIPO_UTIL ? 'dias úteis' : 'dias corridos',
            'dobro' => $resultado['dobro'],
            'feriados' => $feriadosResumo,
        ]];

        $summary = $resultado['explicacao'];

        $processoId = (int) ($params['processo_id'] ?? 0);
        if ($processoId > 0) {
            $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
            $processo = $empresa ? $this->processoRepo->findOneByEmpresa($empresa, $processoId) : null;

            if ($processo !== null) {
                $titulo = trim((string) ($params['titulo'] ?? '')) ?: 'Prazo calculado pela Bruna';

                $results[] = $this->pedirConfirmacao(
                    'criar_tarefa',
                    [
                        'processo_id' => $processo->getId(),
                        'titulo' => $titulo,
                        'prazo' => $resultado['data_final']->format('Y-m-d'),
                        'descricao' => $resultado['explicacao'],
                    ],
                    'Anotar esse vencimento como tarefa?',
                    [
                        ['label' => 'Processo', 'value' => $processo->getNumero()],
                        ['label' => 'Título', 'value' => $titulo],
                        ['label' => 'Vencimento', 'value' => $resultado['data_final']->format('d/m/Y')],
                    ],
                    'Sim, criar tarefa',
                );
                $summary .= ' Já deixo anotado como tarefa nesse processo, se quiser.';
            }
        }

        return ['summary' => $summary, 'results' => $results];
    }
}
