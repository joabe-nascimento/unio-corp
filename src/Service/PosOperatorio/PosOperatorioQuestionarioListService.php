<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioQuestionarioResposta;
use App\Repository\PosOperatorioQuestionarioRespostaRepository;

final class PosOperatorioQuestionarioListService
{
    public function __construct(
        private PosOperatorioQuestionarioRespostaRepository $repository,
    ) {}

    /** @return array{items: list<array<string, mixed>>, stats: array{hoje: int, pendentes: int, total: int}} */
    public function buildList(Empresa $empresa, int $limit = 50): array
    {
        $rows = $this->repository->findRecentByEmpresa($empresa, $limit);
        $today = new \DateTimeImmutable('today');

        $items = array_map(function (PosOperatorioQuestionarioResposta $qr) {
            $p = $qr->getPaciente();

            return [
                'id' => $qr->getId(),
                'paciente_id' => $p->getId(),
                'paciente_codigo' => $p->getCodigo(),
                'paciente_nome' => $p->getNome(),
                'dia_pos' => $p->getDiaPosOperatorio(),
                'data' => $qr->getDataReferencia()->format('d/m/Y'),
                'score' => $qr->getScoreRisco(),
                'respondido_em' => $qr->getRespondidoEm()->format('d/m/Y H:i'),
                'alerta_gerado' => $qr->isAlertaGerado(),
                'dor' => $qr->getRespostas()['dor'] ?? '—',
                'febre' => $qr->getRespostas()['febre'] ?? '—',
            ];
        }, $rows);

        return [
            'items' => $items,
            'stats' => [
                'hoje' => $this->repository->countByEmpresaOnDate($empresa, $today),
                'total' => \count($items),
                'pendentes' => $this->repository->countPacientesPendentesHoje($empresa, $today),
                'alertas' => \count(array_filter($items, static fn (array $row): bool => (bool) ($row['alerta_gerado'] ?? false))),
            ],
        ];
    }
}
