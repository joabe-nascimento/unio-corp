<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Service\Juridico\JuridicoJurisprudenciaService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Salva um julgado (já pesquisado com IA) na biblioteca de jurisprudência do
 * escritório. Normalmente é chamada com `confirmado: true` direto do botão que
 * já aparece junto de cada resultado de pesquisa — mas também aceita ser pedida
 * em texto livre, quando então pede confirmação antes de gravar.
 */
final class SalvarJurisprudenciaTool implements SashaToolInterface
{
    use ConfirmableToolTrait;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoJurisprudenciaService $jurisprudencia,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'salvar_jurisprudencia';
    }

    public function getDescription(): string
    {
        return 'Salva um julgado pesquisado na biblioteca de jurisprudência do escritório';
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
        $tribunal = trim((string) ($params['tribunal'] ?? ''));
        if ($tema === '' || $tribunal === '') {
            return ['summary' => 'Faltam dados desse julgado pra eu salvar — preciso pelo menos do tribunal e do tema.', 'results' => []];
        }

        if (!$this->confirmado($params)) {
            $preview = [
                ['label' => 'Tribunal', 'value' => $tribunal],
                ['label' => 'Tema', 'value' => $tema],
            ];
            if (!empty($params['resultado'])) {
                $preview[] = ['label' => 'Resultado', 'value' => (string) $params['resultado']];
            }

            return [
                'summary' => 'Quer que eu guarde esse julgado na biblioteca do escritório?',
                'results' => [$this->pedirConfirmacao(
                    'salvar_jurisprudencia',
                    $params,
                    'Salvar na biblioteca',
                    $preview,
                    'Sim, salvar',
                )],
            ];
        }

        $item = $this->jurisprudencia->salvarSugestao($empresa, $params, $user);

        return [
            'summary' => sprintf('Prontinho — salvei "%s" (%s) na biblioteca.', $item->getTema(), $item->getTribunal()),
            'results' => [[
                'label' => 'Ver na biblioteca',
                'url' => $this->router->generate('app_juridico_jurisprudencia'),
            ]],
        ];
    }
}
