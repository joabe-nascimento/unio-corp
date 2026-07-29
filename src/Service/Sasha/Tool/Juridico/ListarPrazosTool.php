<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\JuridicoPrazo;
use App\Entity\User;
use App\Repository\JuridicoPrazoRepository;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Lista TODOS os prazos pendentes de um recorte da carteira (cliente, número de
 * processo ou todos), diferente de {@see TarefasUrgentesTool} que só mostra os
 * já críticos/atrasados. Determinístico, sem depender de LLM.
 */
final class ListarPrazosTool implements SashaToolInterface
{
    private const LIMITE_EXIBICAO = 15;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoPrazoRepository $prazoRepository,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'listar_prazos';
    }

    public function getDescription(): string
    {
        return 'Lista todos os prazos pendentes de um cliente, processo específico ou de toda a carteira';
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

        $cliente = trim((string) ($params['cliente'] ?? ''));
        $numeroProcesso = trim((string) ($params['numero_processo'] ?? $params['numero'] ?? ''));

        $prazos = $this->prazoRepository->findForEmpresa($empresa, 'pendentes');

        if ($cliente !== '') {
            $prazos = array_values(array_filter(
                $prazos,
                static fn (JuridicoPrazo $p) => $p->getProcesso() !== null
                    && $p->getProcesso()->getCliente() !== null
                    && mb_stripos($p->getProcesso()->getCliente()->getNome(), $cliente) !== false,
            ));
        } elseif ($numeroProcesso !== '') {
            $prazos = array_values(array_filter(
                $prazos,
                static fn (JuridicoPrazo $p) => $p->getProcesso() !== null
                    && str_contains($p->getProcesso()->getNumero(), $numeroProcesso),
            ));
        }

        if ($prazos === []) {
            $recorte = $cliente !== '' ? sprintf(' para "%s"', $cliente) : ($numeroProcesso !== '' ? sprintf(' para o processo %s', $numeroProcesso) : '');

            return ['summary' => sprintf('Nenhum prazo pendente encontrado%s.', $recorte), 'results' => []];
        }

        $hoje = new \DateTimeImmutable('today');
        $atrasados = array_values(array_filter($prazos, static fn (JuridicoPrazo $p) => $p->getDataLimite() < $hoje));
        $aVencer = array_values(array_filter($prazos, static fn (JuridicoPrazo $p) => $p->getDataLimite() >= $hoje));

        $recorte = $cliente !== '' ? sprintf(' de "%s"', $cliente) : ($numeroProcesso !== '' ? sprintf(' do processo %s', $numeroProcesso) : ' da carteira');
        $summary = sprintf(
            '%d prazo(s) pendente(s)%s: %d atrasado(s) e %d a vencer.',
            \count($prazos),
            $recorte,
            \count($atrasados),
            \count($aVencer),
        );
        if (\count($prazos) > self::LIMITE_EXIBICAO) {
            $summary .= sprintf(' Mostrando os %d mais próximos.', self::LIMITE_EXIBICAO);
        }

        $ordenados = array_merge($atrasados, $aVencer);
        $results = [];
        foreach (\array_slice($ordenados, 0, self::LIMITE_EXIBICAO) as $prazo) {
            $processo = $prazo->getProcesso();
            $statusIcon = $prazo->getDataLimite() < $hoje ? '⚠ Atrasado' : sprintf('vence %s', $prazo->getDataLimite()->format('d/m/Y'));
            $label = sprintf(
                '%s — %s (%s)%s',
                $prazo->getTipo(),
                $statusIcon,
                $processo?->getNumero() ?? 'sem processo vinculado',
                $processo?->getCliente() !== null ? ' · ' . $processo->getCliente()->getNome() : '',
            );

            $results[] = [
                'label' => $label,
                'url' => $processo !== null ? $this->router->generate('app_juridico_processo_show', ['id' => $processo->getId()]) : null,
            ];
        }

        return ['summary' => $summary, 'results' => $results];
    }
}
