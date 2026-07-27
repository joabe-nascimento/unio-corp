<?php

namespace App\Service\Vitoria\Tool\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Entity\User;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Juridico\JuridicoProcessoTarefaService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Vitoria\VitoriaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Cria uma tarefa em um processo a pedido da Bruna — sempre com uma prévia antes
 * de gravar. Nada entra na base sem o usuário confirmar o que vai ser criado.
 */
final class CriarTarefaTool implements VitoriaToolInterface
{
    use ConfirmableToolTrait;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoProcessoTarefaService $tarefaService,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'criar_tarefa';
    }

    public function getDescription(): string
    {
        return 'Cria uma tarefa em um processo (com prévia e confirmação antes de gravar)';
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

        $titulo = trim((string) ($params['titulo'] ?? ''));
        if ($titulo === '') {
            return ['summary' => 'Me diga o título da tarefa (ex.: "Contestação", "Audiência de conciliação") e em qual processo.', 'results' => []];
        }

        $resolvido = $this->resolverProcesso($empresa, $params);
        if ($resolvido === null) {
            return ['summary' => 'Não encontrei esse processo por aqui — confere o número pra mim?', 'results' => []];
        }
        if (\is_array($resolvido)) {
            return [
                'summary' => sprintf('Encontrei %d processos parecidos — qual deles é?', \count($resolvido)),
                'results' => $resolvido,
            ];
        }

        $processo = $resolvido;
        $prazoRaw = trim((string) ($params['prazo'] ?? ''));
        $prazo = $prazoRaw !== '' ? \DateTimeImmutable::createFromFormat('Y-m-d', $prazoRaw) ?: null : null;
        $descricao = $params['descricao'] ?? null;

        if (!$this->confirmado($params)) {
            $preview = [
                ['label' => 'Processo', 'value' => $processo->getNumero()],
                ['label' => 'Título', 'value' => $titulo],
            ];
            if ($prazo !== null) {
                $preview[] = ['label' => 'Vencimento', 'value' => $prazo->format('d/m/Y')];
            }

            return [
                'summary' => sprintf('Posso criar a tarefa "%s" no processo %s.', $titulo, $processo->getNumero()),
                'results' => [$this->pedirConfirmacao(
                    'criar_tarefa',
                    [
                        'processo_id' => $processo->getId(),
                        'titulo' => $titulo,
                        'prazo' => $prazoRaw,
                        'descricao' => $descricao,
                    ],
                    'Criar tarefa no processo',
                    $preview,
                    'Sim, criar tarefa',
                )],
            ];
        }

        $tarefa = $this->tarefaService->create($processo, [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'prazo' => $prazoRaw !== '' ? $prazoRaw : null,
        ]);

        return [
            'summary' => sprintf('Prontinho — criei a tarefa "%s" no processo %s.', $tarefa->getTitulo(), $processo->getNumero()),
            'results' => [[
                'label' => 'Abrir processo ' . $processo->getNumero(),
                'url' => $this->router->generate('app_juridico_processo_show', ['id' => $processo->getId()]),
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return JuridicoProcesso|list<array<string, mixed>>|null
     */
    private function resolverProcesso(Empresa $empresa, array $params): JuridicoProcesso|array|null
    {
        $id = (int) ($params['processo_id'] ?? 0);
        if ($id > 0) {
            return $this->processoRepo->findOneByEmpresa($empresa, $id);
        }

        $numero = trim((string) ($params['numero_processo'] ?? $params['processo'] ?? ''));
        if ($numero === '') {
            return null;
        }

        $found = $this->processoRepo->findForEmpresa($empresa, null, $numero);
        if (\count($found) === 1) {
            return $found[0];
        }
        if (\count($found) > 1) {
            return array_map(fn (JuridicoProcesso $p) => [
                'label' => $p->getNumero() . ($p->getCliente() ? ' — ' . $p->getCliente()->getNome() : ''),
                'url' => $this->router->generate('app_juridico_processo_show', ['id' => $p->getId()]),
            ], \array_slice($found, 0, 5));
        }

        return null;
    }
}
