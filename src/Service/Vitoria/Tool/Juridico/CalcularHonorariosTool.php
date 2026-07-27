<?php

namespace App\Service\Vitoria\Tool\Juridico;

use App\Entity\User;
use App\Service\Juridico\HonorariosCalculator;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Vitoria\VitoriaToolInterface;

final class CalcularHonorariosTool implements VitoriaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private HonorariosCalculator $calculator,
    ) {
    }

    public function getName(): string
    {
        return 'calcular_honorarios';
    }

    public function getDescription(): string
    {
        return 'Estima honorários advocatícios (tabela progressiva + êxito) a partir do valor da causa';
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
        $valorCausa = (float) ($params['valor_causa'] ?? 0);
        if ($valorCausa <= 0) {
            return ['summary' => 'Informe o valor da causa para eu estimar os honorários.', 'results' => []];
        }

        $percentualExito = (float) ($params['percentual_exito'] ?? 0);
        $resultado = $this->calculator->calcular($valorCausa, $percentualExito);

        return [
            'summary' => $resultado['explicacao'],
            'results' => [[
                'label' => 'Honorário total estimado: R$ ' . number_format($resultado['honorario_total_estimado'], 2, ',', '.'),
                'honorario_contratual' => $resultado['honorario_contratual_estimado'],
                'honorario_exito' => $resultado['honorario_exito'],
                'honorario_total' => $resultado['honorario_total_estimado'],
                'faixas' => $resultado['faixas_aplicadas'],
            ]],
        ];
    }
}
