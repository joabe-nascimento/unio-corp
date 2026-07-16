<?php

namespace App\Service\PosOperatorio;

final class ClinicCidCatalogService
{
    /** @var list<array{codigo: string, descricao: string}> */
    private const CATALOG = [
        ['codigo' => 'Z98.8', 'descricao' => 'Outros estados pós-cirúrgicos especificados'],
        ['codigo' => 'Z48.0', 'descricao' => 'Cuidados a curativos e suturas cirúrgicas'],
        ['codigo' => 'Z48.8', 'descricao' => 'Outros cuidados especificados de seguimento cirúrgico'],
        ['codigo' => 'Z48.9', 'descricao' => 'Cuidado de seguimento cirúrgico não especificado'],
        ['codigo' => 'T81.4', 'descricao' => 'Infecção após procedimento não classificada em outra parte'],
        ['codigo' => 'T81.0', 'descricao' => 'Hemorragia e hematoma complicando procedimento'],
        ['codigo' => 'T81.3', 'descricao' => 'Deiscência de ferida operatória'],
        ['codigo' => 'L76.8', 'descricao' => 'Outras complicações de procedimentos cutâneos'],
        ['codigo' => 'L90.5', 'descricao' => 'Cicatriz e fibrose da pele'],
        ['codigo' => 'L91.0', 'descricao' => 'Cicatriz hipertrófica'],
        ['codigo' => 'N62', 'descricao' => 'Hipertrofia mamária'],
        ['codigo' => 'N64.8', 'descricao' => 'Outros transtornos especificados da mama'],
        ['codigo' => 'Q83.8', 'descricao' => 'Outras malformações congênitas da mama'],
        ['codigo' => 'E65', 'descricao' => 'Adiposidade localizada'],
        ['codigo' => 'E66.9', 'descricao' => 'Obesidade não especificada'],
        ['codigo' => 'L98.7', 'descricao' => 'Pele e tecido subcutâneo em excesso e flácido'],
        ['codigo' => 'H02.3', 'descricao' => 'Blefarocalásia'],
        ['codigo' => 'M95.0', 'descricao' => 'Deformidade adquirida do nariz'],
        ['codigo' => 'Q18.8', 'descricao' => 'Outras malformações congênitas especificadas da face e do pescoço'],
        ['codigo' => 'M16.9', 'descricao' => 'Coxartrose não especificada'],
        ['codigo' => 'M17.9', 'descricao' => 'Gonartrose não especificada'],
        ['codigo' => 'M23.2', 'descricao' => 'Transtorno do menisco devido a ruptura ou lesão antiga'],
        ['codigo' => 'M75.1', 'descricao' => 'Síndrome do manguito rotador'],
        ['codigo' => 'M54.5', 'descricao' => 'Dor lombar baixa'],
        ['codigo' => 'M51.1', 'descricao' => 'Transtornos de discos lombares e de outros discos intervertebrais com radiculopatia'],
        ['codigo' => 'S83.5', 'descricao' => 'Entorse e distensão envolvendo ligamento cruzado do joelho'],
        ['codigo' => 'S46.0', 'descricao' => 'Traumatismo do tendão do manguito rotador do ombro'],
        ['codigo' => 'M84.1', 'descricao' => 'Não-união de fratura (pseudoartrose)'],
        ['codigo' => 'Z47.0', 'descricao' => 'Cuidados de seguimento envolvendo remoção de placa e outros dispositivos de fixação interna'],
        ['codigo' => 'Z96.6', 'descricao' => 'Presença de implantes ortopédicos articulares'],
        ['codigo' => 'R52.9', 'descricao' => 'Dor não especificada'],
        ['codigo' => 'R50.9', 'descricao' => 'Febre não especificada'],
        ['codigo' => 'R06.0', 'descricao' => 'Dispneia'],
        ['codigo' => 'I10', 'descricao' => 'Hipertensão essencial (primária)'],
        ['codigo' => 'E11.9', 'descricao' => 'Diabetes mellitus não-insulino-dependente sem complicações'],
        ['codigo' => 'J45.9', 'descricao' => 'Asma não especificada'],
        ['codigo' => 'Z01.8', 'descricao' => 'Outros exames especiais especificados'],
        ['codigo' => 'Z00.0', 'descricao' => 'Exame médico geral'],
        ['codigo' => 'Z76.0', 'descricao' => 'Emissão de receita de repetição'],
        ['codigo' => 'Z51.8', 'descricao' => 'Outro atendimento médico especificado'],
        ['codigo' => 'G43.9', 'descricao' => 'Enxaqueca não especificada'],
        ['codigo' => 'M79.1', 'descricao' => 'Mialgia'],
        ['codigo' => 'M25.5', 'descricao' => 'Dor articular'],
        ['codigo' => 'K21.9', 'descricao' => 'Doença de refluxo gastroesofágico sem esofagite'],
    ];

    /**
     * @return list<array{codigo: string, descricao: string}>
     */
    public function search(string $q, int $limit = 20): array
    {
        $q = trim($q);
        $limit = max(1, min(50, $limit));
        if ($q === '') {
            return \array_slice(self::CATALOG, 0, $limit);
        }

        $needle = mb_strtolower($q);
        $matches = [];
        foreach (self::CATALOG as $item) {
            $hayCodigo = mb_strtolower($item['codigo']);
            $hayDesc = mb_strtolower($item['descricao']);
            if (str_contains($hayCodigo, $needle) || str_contains($hayDesc, $needle)) {
                $matches[] = $item;
                if (\count($matches) >= $limit) {
                    break;
                }
            }
        }

        return $matches;
    }
}
