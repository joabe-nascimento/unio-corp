<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Juridico\JuridicoPrazoService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Registra um compromisso na agenda de "Prazos & Diligências" do escritório —
 * com prévia e confirmação antes de gravar, igual às demais ferramentas de escrita.
 */
final class RegistrarPrazoTool implements SashaToolInterface
{
    use ConfirmableToolTrait;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoPrazoService $prazoService,
        private JuridicoProcessoRepository $processoRepo,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'registrar_prazo';
    }

    public function getDescription(): string
    {
        return 'Registra um prazo na agenda do escritório, com prévia e confirmação antes de gravar';
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

        $tipo = trim((string) ($params['tipo'] ?? ''));
        if ($tipo === '') {
            return ['summary' => 'Me diga qual é o tipo do prazo (ex.: "Contestação", "Audiência", "Recurso") e a data-limite.', 'results' => []];
        }

        $dataRaw = trim((string) ($params['data_limite'] ?? ''));
        $data = $dataRaw !== '' ? \DateTimeImmutable::createFromFormat('Y-m-d', $dataRaw) : false;
        if (!$data) {
            return ['summary' => 'Preciso de uma data-limite válida (ex.: "17/08/2026") para registrar esse prazo.', 'results' => []];
        }

        $processo = null;
        $numero = trim((string) ($params['numero_processo'] ?? ''));
        if ($numero !== '') {
            $encontrados = $this->processoRepo->findForEmpresa($empresa, null, $numero);
            $processo = $encontrados[0] ?? null;
        }

        $descricao = $params['descricao'] ?? null;

        if (!$this->confirmado($params)) {
            $preview = [
                ['label' => 'Tipo', 'value' => $tipo],
                ['label' => 'Data-limite', 'value' => $data->format('d/m/Y')],
            ];
            if ($processo !== null) {
                $preview[] = ['label' => 'Processo', 'value' => $processo->getNumero()];
            }

            return [
                'summary' => sprintf('Posso registrar "%s" na agenda, vencendo em %s.', $tipo, $data->format('d/m/Y')),
                'results' => [$this->pedirConfirmacao(
                    'registrar_prazo',
                    [
                        'tipo' => $tipo,
                        'data_limite' => $dataRaw,
                        'numero_processo' => $numero,
                        'descricao' => $descricao,
                    ],
                    'Registrar prazo na agenda',
                    $preview,
                    'Sim, registrar',
                )],
            ];
        }

        $prazo = $this->prazoService->create($empresa, [
            'tipo' => $tipo,
            'data_limite' => $dataRaw,
            'descricao' => $descricao,
            'processo_id' => $processo?->getId(),
        ]);

        return [
            'summary' => sprintf('Prontinho — "%s" está na agenda, vencendo em %s.', $prazo->getTipo(), $prazo->getDataLimite()->format('d/m/Y')),
            'results' => [[
                'label' => 'Ver agenda de prazos',
                'url' => $this->router->generate('app_juridico_prazos'),
            ]],
        ];
    }
}
