<?php

namespace App\PosOperatorio;

use App\Service\PosOperatorio\PosOperatorioProtocoloDefaults;

/**
 * Biblioteca de protocolos prontos — importáveis por clínica.
 */
final class ClinicProtocolLibrary
{
    /** @return list<array{slug: string, nome: string, tipo: string, duracao_dias: int, descricao: string, checklist: list<array<string, mixed>>, regras: array<string, mixed>}> */
    public static function templates(): array
    {
        return [
            self::template(
                'apendicectomia-lap',
                'Apendicectomia laparoscópica',
                'apendicectomia-lap',
                14,
                'Recuperação pós-apendicectomia com foco em dor, febre e retorno gradual.',
                [
                    ['dia' => 1, 'item' => 'Repouso relativo e hidratação'],
                    ['dia' => 3, 'item' => 'Retirada de curativo e deambulação leve'],
                    ['dia' => 7, 'item' => 'Retorno ambulatorial'],
                    ['dia' => 14, 'item' => 'Alta do acompanhamento remoto'],
                ],
            ),
            self::template(
                'herniorrafia-inguinal',
                'Herniorrafia inguinal',
                'herniorrafia-inguinal',
                21,
                'Controle de dor, edema e sinais de complicação na região inguinal.',
                [
                    ['dia' => 1, 'item' => 'Gelo local e repouso relativo'],
                    ['dia' => 5, 'item' => 'Evitar esforço abdominal'],
                    ['dia' => 7, 'item' => 'Retorno ambulatorial'],
                    ['dia' => 14, 'item' => 'Retomada de atividades leves'],
                    ['dia' => 21, 'item' => 'Encerramento do protocolo'],
                ],
            ),
            self::template(
                'colecistectomia',
                'Colecistectomia videolaparoscópica',
                'colecistectomia',
                14,
                'Acompanhamento de náusea, dor abdominal e retorno alimentar.',
                [
                    ['dia' => 1, 'item' => 'Dieta leve conforme orientação'],
                    ['dia' => 3, 'item' => 'Retirada de curativos'],
                    ['dia' => 7, 'item' => 'Retorno ambulatorial'],
                ],
            ),
            self::template(
                'artroscopia-joelho',
                'Artroscopia de joelho',
                'artroscopia-joelho',
                28,
                'Mobilização progressiva e controle de edema pós-procedimento.',
                [
                    ['dia' => 1, 'item' => 'Elevação do membro e gelo'],
                    ['dia' => 7, 'item' => 'Início de fisioterapia orientada'],
                    ['dia' => 14, 'item' => 'Retorno ortopédico'],
                    ['dia' => 28, 'item' => 'Alta do acompanhamento'],
                ],
            ),
            self::template(
                'cesariana',
                'Cesárea eletiva',
                'cesariana',
                42,
                'Recuperação puerperal com monitoramento de dor, febre e cicatrização.',
                [
                    ['dia' => 1, 'item' => 'Repouso e amamentação assistida'],
                    ['dia' => 7, 'item' => 'Retirada de pontos se indicado'],
                    ['dia' => 14, 'item' => 'Retorno obstétrico'],
                    ['dia' => 42, 'item' => 'Encerramento pós-parto'],
                ],
            ),
            self::template(
                'mamoplastia-reducao',
                'Mamoplastia redução',
                'mamoplastia-reducao',
                28,
                'Controle de dor, edema mamário e cicatrização.',
                self::checklistPadrao(28),
            ),
            self::template(
                'artroplastia-quadril',
                'Artroplastia de quadril',
                'artroplastia-quadril',
                42,
                'Reabilitação progressiva e prevenção de luxação.',
                [
                    ['dia' => 1, 'item' => 'Deambulação assistida'],
                    ['dia' => 7, 'item' => 'Fisioterapia e analgesia'],
                    ['dia' => 14, 'item' => 'Retorno ortopédico'],
                    ['dia' => 42, 'item' => 'Alta do acompanhamento'],
                ],
            ),
            self::template(
                'tiroidectomia',
                'Tiroidectomia',
                'tiroidectomia',
                14,
                'Monitoramento de dor cervical, rouquidão e sinais de hipocalcemia.',
                self::checklistPadrao(14),
            ),
            self::template(
                'septoplastia',
                'Septoplastia',
                'septoplastia',
                14,
                'Controle de epistaxe, edema nasal e respiração.',
                self::checklistPadrao(14),
            ),
            self::template(
                'histerectomia',
                'Histerectomia',
                'histerectomia',
                28,
                'Recuperação pós-histerectomia com foco em dor e sangramento.',
                self::checklistPadrao(28),
            ),
            self::template(
                'laparoscopia',
                'Laparoscopia diagnóstica',
                'laparoscopia',
                7,
                'Acompanhamento breve pós-laparoscopia diagnóstica.',
                self::checklistPadrao(7),
            ),
            self::template(
                'bariatrica',
                'Cirurgia bariátrica',
                'bariatrica',
                28,
                'Dieta progressiva, hidratação e sinais de deiscência.',
                [
                    ['dia' => 1, 'item' => 'Dieta líquida conforme protocolo'],
                    ['dia' => 7, 'item' => 'Retorno nutricional'],
                    ['dia' => 14, 'item' => 'Retorno cirúrgico'],
                    ['dia' => 28, 'item' => 'Encerramento fase aguda'],
                ],
            ),
            self::template(
                'protese-joelho',
                'Prótese total de joelho',
                'protese-joelho',
                42,
                'Mobilização, analgesia e fisioterapia pós-artroplastia.',
                [
                    ['dia' => 1, 'item' => 'Deambulação com auxílio'],
                    ['dia' => 7, 'item' => 'Fisioterapia diária'],
                    ['dia' => 14, 'item' => 'Retorno ortopédico'],
                    ['dia' => 42, 'item' => 'Alta do acompanhamento'],
                ],
            ),
            self::template(
                'mastectomia',
                'Mastectomia',
                'mastectomia',
                28,
                'Cicatrização, drenagem e suporte emocional pós-mastectomia.',
                self::checklistPadrao(28),
            ),
            self::template(
                'facectomia',
                'Cirurgia de catarata',
                'facectomia',
                14,
                'Colírios, proteção ocular e sinais de infecção.',
                self::checklistPadrao(14),
            ),
            self::template(
                'revascularizacao',
                'Revascularização miocárdica',
                'revascularizacao',
                42,
                'Recuperação cardíaca, ferida esternal e reabilitação.',
                [
                    ['dia' => 1, 'item' => 'Monitorização de ferida esternal'],
                    ['dia' => 7, 'item' => 'Retorno cardiológico'],
                    ['dia' => 14, 'item' => 'Início de reabilitação cardíaca'],
                    ['dia' => 42, 'item' => 'Encerramento fase aguda'],
                ],
            ),
            self::template(
                'uretrotomia',
                'Uretrotomia',
                'uretrotomia',
                14,
                'Controle de dor, micção e sinais de infecção urinária.',
                self::checklistPadrao(14),
            ),
            self::template(
                'fratura-tibia',
                'Osteossíntese de fratura (tíbia)',
                'fratura-tibia',
                56,
                'Imobilização, analgesia e retorno funcional progressivo.',
                [
                    ['dia' => 1, 'item' => 'Elevação e analgesia'],
                    ['dia' => 7, 'item' => 'Retorno ortopédico e curativo'],
                    ['dia' => 14, 'item' => 'Início de carga conforme orientação'],
                    ['dia' => 56, 'item' => 'Alta do acompanhamento'],
                ],
            ),
            self::template(
                'lipoaspiracao',
                'Lipoaspiração',
                'lipoaspiracao',
                28,
                'Controle de edema, drenagem e sinais de infecção pós-lipo.',
                self::checklistPadrao(28),
            ),
            self::template(
                'blefaroplastia',
                'Blefaroplastia',
                'blefaroplastia',
                14,
                'Edema periorbital, visão e cicatrização de pálpebras.',
                self::checklistPadrao(14),
            ),
            self::template(
                'abdominoplastia',
                'Abdominoplastia',
                'abdominoplastia',
                42,
                'Drenos, deambulação e vigilância de deiscência/seroma.',
                [
                    ['dia' => 1, 'item' => 'Repouso relativo e cuidados com dreno'],
                    ['dia' => 7, 'item' => 'Retorno para avaliação de ferida'],
                    ['dia' => 14, 'item' => 'Retorno cirúrgico'],
                    ['dia' => 42, 'item' => 'Encerramento fase aguda'],
                ],
            ),
            self::template(
                'rinoplastia',
                'Rinoplastia',
                'rinoplastia',
                21,
                'Edema nasal, epistaxe e proteção do splint.',
                self::checklistPadrao(21),
            ),
            self::template(
                'pre-op-geral',
                'Pré-op geral + pós-op básico',
                'pre-op-geral',
                14,
                'Trilha completa: preparação (D−7 a D0) e recuperação inicial (D+1 a D+7).',
                array_merge(
                    PosOperatorioProtocoloDefaults::checklistPreOp(),
                    PosOperatorioProtocoloDefaults::checklistBasico(),
                ),
                PosOperatorioProtocoloDefaults::perguntasPreOp(),
            ),
            self::template(
                'pre-op-ortognatica',
                'Pré-op ortognática + pós',
                'ortognatica',
                42,
                'Preparação reforçada (D−14 a D0) e acompanhamento com drenos/ferida.',
                array_merge(
                    PosOperatorioProtocoloDefaults::checklistPreOpOrtognatica(),
                    [
                        ['dia' => 1, 'item' => 'Repouso relativo e cuidados com dreno'],
                        ['dia' => 7, 'item' => 'Retorno para avaliação de ferida'],
                        ['dia' => 14, 'item' => 'Retorno cirúrgico'],
                    ],
                ),
                PosOperatorioProtocoloDefaults::perguntasPreOp(),
            ),
            self::template(
                'pre-op-rinoplastia',
                'Pré-op rinoplastia + pós',
                'rinoplastia',
                21,
                'Preparação nasal (D−7 a D0) e proteção do splint no pós.',
                array_merge(
                    PosOperatorioProtocoloDefaults::checklistPreOpRinoplastia(),
                    self::checklistPadrao(21),
                ),
                PosOperatorioProtocoloDefaults::perguntasPreOp(),
            ),
            self::template(
                'pre-op-cesarea',
                'Pré-op cesárea + pós imediato',
                'cesarea',
                14,
                'Preparação obstétrica (D−7 a D0) e recuperação puerperal inicial.',
                array_merge(
                    PosOperatorioProtocoloDefaults::checklistPreOpCesarea(),
                    PosOperatorioProtocoloDefaults::checklistBasico(),
                ),
                PosOperatorioProtocoloDefaults::perguntasPreOp(),
            ),
        ];
    }

    /** @return list<array{dia: int, item: string}> */
    private static function checklistPadrao(int $duracaoDias): array
    {
        $marcos = array_unique(array_filter([
            1,
            min(3, $duracaoDias),
            min(7, $duracaoDias),
            $duracaoDias > 7 ? min(14, $duracaoDias) : null,
            $duracaoDias,
        ]));

        sort($marcos);

        $itens = [
            1 => 'Repouso relativo e hidratação',
            3 => 'Verificar curativo / ponto cirúrgico',
            7 => 'Retorno ambulatorial se agendado',
            14 => 'Reavaliação clínica',
        ];

        $checklist = [];
        foreach ($marcos as $dia) {
            $checklist[] = [
                'dia' => $dia,
                'item' => $itens[$dia] ?? ($dia >= $duracaoDias ? 'Encerramento do protocolo' : 'Acompanhamento clínico'),
            ];
        }

        return $checklist;
    }

    /** @return array{slug: string, nome: string, tipo: string, duracao_dias: int, descricao: string, checklist: list<array<string, mixed>>, regras: array<string, mixed>}|null */
    public static function find(string $slug): ?array
    {
        foreach (self::templates() as $tpl) {
            if ($tpl['slug'] === $slug) {
                return $tpl;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>>|null $perguntas
     *
     * @return array{slug: string, nome: string, tipo: string, duracao_dias: int, descricao: string, checklist: list<array<string, mixed>>, regras: array<string, mixed>, perguntas: list<array<string, mixed>>}
     */
    private static function template(
        string $slug,
        string $nome,
        string $tipo,
        int $dias,
        string $descricao,
        array $checklist,
        ?array $perguntas = null,
    ): array {
        return [
            'slug' => $slug,
            'nome' => $nome,
            'tipo' => $tipo,
            'duracao_dias' => $dias,
            'descricao' => $descricao,
            'checklist' => $checklist,
            'regras' => PosOperatorioProtocoloDefaults::regrasAlerta(),
            'perguntas' => $perguntas ?? PosOperatorioProtocoloDefaults::perguntas(),
        ];
    }
}
