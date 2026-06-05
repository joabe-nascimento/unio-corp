<?php

namespace App\Service\Integracoes;

use App\Entity\Empresa;
use App\Entity\IntegMapeamento;
use App\Entity\IntegShadowRun;
use App\Repository\IntegMapeamentoRepository;
use App\Repository\IntegShadowRunRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoShadowReplayService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegMapeamentoRepository $mapRepo,
        private IntegShadowRunRepository $runRepo,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listRecentRuns(Empresa $empresa, int $limit = 5): array
    {
        return array_map(
            static fn (IntegShadowRun $r) => $r->toArray(),
            $this->runRepo->findRecentForEmpresa($empresa, $limit),
        );
    }

    /** @return array<string, mixed> */
    public function runSimulation(Empresa $empresa, int $mapId, string $destinoProposto, int $periodoDias = 7): array
    {
        $map = $this->mapRepo->findOneForEmpresa($empresa, $mapId);
        if ($map === null) {
            throw new \InvalidArgumentException('Mapeamento não encontrado.');
        }

        $destinoProposto = trim($destinoProposto);
        if ($destinoProposto === '') {
            throw new \InvalidArgumentException('Campo destino proposto é obrigatório.');
        }

        $periodoDias = max(1, min(30, $periodoDias));
        $result = $this->simulate($map, $destinoProposto, $periodoDias);

        $run = (new IntegShadowRun())
            ->setEmpresa($empresa)
            ->setMapeamento($map)
            ->setMapeamentoNome($map->getNome())
            ->setCampoOrigem($map->getCampoOrigem())
            ->setCampoDestinoAtual($map->getCampoDestino())
            ->setCampoDestinoProposto($destinoProposto)
            ->setPeriodoDias($periodoDias)
            ->setTotalEventos($result['total'])
            ->setSucesso($result['sucesso'])
            ->setFalhas($result['falhas'])
            ->setDuplicatas($result['duplicatas'])
            ->setAmostras($result['amostras']);

        $this->em->persist($run);
        $this->em->flush();

        return $run->toArray();
    }

    /**
     * @return array{total: int, sucesso: int, falhas: int, duplicatas: int, amostras: list<array<string, mixed>>}
     */
    private function simulate(IntegMapeamento $map, string $destinoProposto, int $periodoDias): array
    {
        $seed = crc32($map->getCampoOrigem() . '|' . $destinoProposto . '|' . $periodoDias);
        mt_srand($seed);

        $baseEvents = match ($periodoDias) {
            1 => 180,
            14 => 2400,
            30 => 4800,
            default => 1284,
        };

        $changePenalty = $destinoProposto === $map->getCampoDestino() ? 0 : 1;
        $failRate = $changePenalty ? 0.008 + (strlen($destinoProposto) % 7) * 0.001 : 0.002;
        $dupRate = $changePenalty ? 0.003 : 0.001;

        $falhas = (int) round($baseEvents * $failRate);
        $duplicatas = (int) round($baseEvents * $dupRate);
        $sucesso = $baseEvents - $falhas - $duplicatas;

        $amostras = [];
        $samples = [
            ['payload' => 'joana.silva@empresa.com', 'atual' => 'joana.silva@empresa.com', 'proposto' => 'joana.silva@empresa.com', 'resultado' => 'ok'],
            ['payload' => 'CARLOS.MENDES@EMPRESA.COM', 'atual' => 'carlos.mendes@empresa.com', 'proposto' => 'carlos.mendes@empresa.com', 'resultado' => 'ok'],
            ['payload' => 'ana_p@empresa.com', 'atual' => 'ana_p@empresa.com', 'proposto' => '', 'resultado' => 'fail', 'motivo' => 'Campo destino rejeitado pelo AD'],
            ['payload' => 'dup.user@empresa.com', 'atual' => 'dup.user@empresa.com', 'proposto' => 'dup.user@empresa.com', 'resultado' => 'dup', 'motivo' => 'Conta já existente'],
            ['payload' => 'novo.colab@empresa.com', 'atual' => 'novo.colab@empresa.com', 'proposto' => 'novo.colab@empresa.com', 'resultado' => 'ok'],
        ];

        foreach ($samples as $i => $row) {
            if ($changePenalty && $row['resultado'] === 'ok' && $i === 2) {
                $row['proposto'] = '(vazio — mapping inválido)';
            }
            $amostras[] = array_merge($row, [
                'evento' => 'rh.admissao.concluida',
                'timestamp' => (new \DateTimeImmutable('-' . ($i + 1) . ' hours'))->format('d/m H:i'),
            ]);
        }

        return [
            'total' => $baseEvents,
            'sucesso' => max(0, $sucesso),
            'falhas' => $falhas,
            'duplicatas' => $duplicatas,
            'amostras' => $amostras,
        ];
    }
}
