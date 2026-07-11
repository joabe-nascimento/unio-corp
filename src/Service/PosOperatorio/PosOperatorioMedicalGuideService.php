<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioPaciente;

final class PosOperatorioMedicalGuideService
{
    public function __construct(
        private ClinicGuiaMedicoService $guiaStore,
    ) {}

    /** @return array<string, mixed> */
    public function buildGuia(PosOperatorioPaciente $paciente, ?int $diaPos, array $checklistProgress): array
    {
        $custom = $this->guiaStore->resolveForPaciente($paciente);
        $fase = $this->faseAtual($diaPos);

        if ($custom !== null) {
            return [
                'titulo' => 'Guia médico',
                'subtitulo' => $custom['subtitulo'] ?? 'Orientações personalizadas do seu protocolo',
                'fase_label' => $fase['label'],
                'fase_descricao' => $fase['descricao'],
                'guia_nome' => $custom['nome'] ?? null,
                'marcos' => $this->mapMarcos($checklistProgress),
                'orientacoes' => $this->orientacoesFromGuia($custom, $diaPos, $paciente),
                'sinais_alerta' => $custom['sinais_alerta'] ?? [],
                'contato_rapido' => $custom['contato_rapido'] ?? 'Use "Preciso de ajuda" no portal ou ligue para a clínica.',
            ];
        }

        $procedimento = mb_strtolower($this->procedimentoLabel($paciente));

        return [
            'titulo' => 'Guia médico',
            'subtitulo' => 'Orientações personalizadas do seu protocolo',
            'fase_label' => $fase['label'],
            'fase_descricao' => $fase['descricao'],
            'marcos' => $this->mapMarcos($checklistProgress),
            'orientacoes' => $this->orientacoesPorFase($diaPos, $procedimento),
            'sinais_alerta' => [
                'Dor intensa que não melhora com medicação prescrita',
                'Febre acima de 38 °C ou calafrios',
                'Sangramento intenso ou secreção com odor forte',
                'Falta de ar, tontura persistente ou confusão',
            ],
            'contato_rapido' => 'Use "Preciso de ajuda" no portal ou ligue para a clínica em horário comercial.',
        ];
    }

    /**
     * @param array<string, mixed> $guia
     *
     * @return list<string>
     */
    private function orientacoesFromGuia(array $guia, ?int $diaPos, PosOperatorioPaciente $paciente): array
    {
        $base = match (true) {
            $diaPos === null => [
                'Confirme com a clínica a data da cirurgia e o protocolo vinculado.',
            ],
            $diaPos <= 2 => $guia['orientacoes_aguda'] ?? [],
            $diaPos <= 7 => $guia['orientacoes_intermediaria'] ?? [],
            $diaPos <= 14 => $guia['orientacoes_retorno'] ?? [],
            default => $guia['orientacoes_alta'] ?? [],
        };

        if ($base === []) {
            return $this->orientacoesPorFase($diaPos, mb_strtolower($this->procedimentoLabel($paciente)));
        }

        return $base;
    }

    /**
     * @param list<array{dia: int, item: string, done: bool, current: bool}> $checklistProgress
     *
     * @return list<array{dia: int, item: string, state: string}>
     */
    private function mapMarcos(array $checklistProgress): array
    {
        return array_map(static function (array $step): array {
            $state = 'future';
            if ($step['done']) {
                $state = 'done';
            } elseif ($step['current']) {
                $state = 'current';
            }

            return [
                'dia' => $step['dia'],
                'item' => $step['item'],
                'state' => $state,
            ];
        }, $checklistProgress);
    }

    /** @return array{label: string, descricao: string} */
    private function faseAtual(?int $diaPos): array
    {
        if ($diaPos === null) {
            return [
                'label' => 'Pré-protocolo',
                'descricao' => 'Aguardando início formal do acompanhamento pós-operatório.',
            ];
        }

        return match (true) {
            $diaPos <= 2 => [
                'label' => 'Fase aguda',
                'descricao' => 'Primeiros dias: repouso, analgesia e hidratação conforme orientação médica.',
            ],
            $diaPos <= 7 => [
                'label' => 'Fase intermediária',
                'descricao' => 'Recuperação funcional gradual. Observe curativo, mobilização e sinais de infecção.',
            ],
            $diaPos <= 14 => [
                'label' => 'Fase de retorno',
                'descricao' => 'Prepare o retorno ambulatorial e mantenha o questionário diário em dia.',
            ],
            default => [
                'label' => 'Alta monitorada',
                'descricao' => 'Acompanhamento de encerramento. Mantenha contato se surgirem novos sintomas.',
            ],
        };
    }

    /** @return list<string> */
    private function orientacoesPorFase(?int $diaPos, string $procedimento): array
    {
        $base = match (true) {
            $diaPos === null => [
                'Confirme com a clínica a data da cirurgia e o protocolo vinculado.',
            ],
            $diaPos <= 2 => [
                'Priorize repouso relativo e ingestão líquida adequada.',
                'Tome os medicamentos nos horários indicados na receita.',
                'Não retire curativos sem orientação da equipe.',
            ],
            $diaPos <= 7 => [
                'Caminhe pequenas distâncias se liberado pelo médico.',
                'Observe vermelhidão, calor ou secreção no sítio cirúrgico.',
                'Responda o questionário diário para a equipe acompanhar sua evolução.',
            ],
            default => [
                'Confirme retornos e exames agendados.',
                'Retome atividades leves de forma progressiva.',
                'Mantenha contato se notar piora dos sintomas.',
            ],
        };

        if (str_contains($procedimento, 'herni') || str_contains($procedimento, 'hérni')) {
            $base[] = 'Evite esforço abdominal e levantar peso nas primeiras semanas.';
        }
        if (str_contains($procedimento, 'apend') || str_contains($procedimento, 'colecist')) {
            $base[] = 'Siga a dieta progressiva indicada no alta hospitalar.';
        }

        return $base;
    }

    private function procedimentoLabel(PosOperatorioPaciente $paciente): string
    {
        $fromField = trim((string) ($paciente->getProcedimento() ?? ''));
        if ($fromField !== '') {
            return $fromField;
        }

        return trim((string) ($paciente->getProtocolo()?->getNome() ?? 'Procedimento'));
    }
}
