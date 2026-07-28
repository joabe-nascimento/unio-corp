<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Service\Juridico\JuridicoProcessoTarefaService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TarefasUrgentesTool implements SashaToolInterface
{
    private const DIAS_PROXIMO = 3;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoProcessoTarefaService $tarefaService,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'tarefas_urgentes';
    }

    public function getDescription(): string
    {
        return 'Lista tarefas atrasadas ou vencendo em breve em toda a carteira';
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

        $pendentes = $this->tarefaService->findPendentesForEmpresa($empresa);
        $agora = new \DateTimeImmutable();
        $limite = $agora->modify('+' . self::DIAS_PROXIMO . ' days');

        $atrasadas = [];
        $proximas = [];
        foreach ($pendentes as $tarefa) {
            if ($tarefa->getPrazo() === null) {
                continue;
            }
            if ($tarefa->getPrazo() < $agora) {
                $atrasadas[] = $tarefa;
            } elseif ($tarefa->getPrazo() <= $limite) {
                $proximas[] = $tarefa;
            }
        }

        if ($atrasadas === [] && $proximas === []) {
            return ['summary' => 'Nenhuma tarefa atrasada ou vencendo nos próximos dias. Carteira em dia.', 'results' => []];
        }

        $summary = sprintf(
            '%d tarefa(s) atrasada(s) e %d vencendo nos próximos %d dias.',
            \count($atrasadas),
            \count($proximas),
            self::DIAS_PROXIMO,
        );

        $results = [];
        foreach (\array_slice($atrasadas, 0, 5) as $tarefa) {
            $results[] = [
                'label' => sprintf('⚠ Atrasada — %s (%s)', $tarefa->getTitulo(), $tarefa->getProcesso()->getNumero()),
                'url' => $this->router->generate('app_juridico_processo_show', ['id' => $tarefa->getProcesso()->getId()]),
            ];
        }
        foreach (\array_slice($proximas, 0, 5) as $tarefa) {
            $results[] = [
                'label' => sprintf('%s — %s (vence %s)', $tarefa->getTitulo(), $tarefa->getProcesso()->getNumero(), $tarefa->getPrazo()?->format('d/m')),
                'url' => $this->router->generate('app_juridico_processo_show', ['id' => $tarefa->getProcesso()->getId()]),
            ];
        }

        return ['summary' => $summary, 'results' => $results];
    }
}
