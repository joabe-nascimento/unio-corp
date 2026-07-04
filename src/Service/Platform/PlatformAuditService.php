<?php

namespace App\Service\Platform;

use App\Entity\PlatformAuditLog;
use App\Entity\User;
use App\Repository\PlatformAuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class PlatformAuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlatformAuditLogRepository $repo,
    ) {}

    public function record(
        string $categoria,
        string $acao,
        string $resultado,
        string $mensagem,
        ?User $actor = null,
        ?string $actorEmail = null,
        ?string $alvoTipo = null,
        ?int $alvoId = null,
        ?string $alvoRotulo = null,
        ?Request $request = null,
        ?array $payload = null,
    ): PlatformAuditLog {
        $entry = new PlatformAuditLog();
        $entry->setCategoria($categoria);
        $entry->setAcao($acao);
        $entry->setResultado($resultado);
        $entry->setMensagem($mensagem);
        $entry->setAlvoTipo($alvoTipo);
        $entry->setAlvoId($alvoId);
        $entry->setAlvoRotulo($alvoRotulo);
        $entry->setPayload($payload);

        if ($actor !== null) {
            $entry->setActor($actor);
        } elseif ($actorEmail !== null && $actorEmail !== '') {
            $entry->setActorEmail($actorEmail);
        }

        if ($request !== null) {
            $entry->setIp($request->getClientIp());
            $entry->setRota($request->attributes->get('_route'));
        }

        try {
            $this->em->persist($entry);
            $this->em->flush();
        } catch (\Throwable) {
            // Auditoria não deve derrubar login, deploy ou fluxos críticos.
            if ($this->em->isOpen()) {
                $this->em->clear(PlatformAuditLog::class);
            }
        }

        return $entry;
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array{page: int, per_page: int, total: int}}
     */
    public function paginateRows(
        int $page,
        int $perPage,
        string $categoria = '',
        string $acao = '',
        string $resultado = '',
        string $search = '',
    ): array {
        $result = $this->repo->paginate($page, $perPage, $categoria, $acao, $resultado, $search);
        $rows = [];
        foreach ($result['items'] as $item) {
            $rows[] = $item->toRow();
        }

        return [
            'items' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function summaryLast24h(): array
    {
        $since = new \DateTimeImmutable('-24 hours');

        return [
            'outcomes' => $this->repo->countOutcomesSince($since),
            'categories' => $this->repo->countByCategorySince($since),
            'recent' => array_map(
                static fn (PlatformAuditLog $log) => $log->toRow(),
                $this->repo->findRecent(8),
            ),
        ];
    }
}
