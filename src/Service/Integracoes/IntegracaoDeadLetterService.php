<?php

namespace App\Service\Integracoes;

use App\Entity\Empresa;
use App\Entity\IntegConector;
use App\Entity\IntegDeadLetter;
use App\Repository\IntegDeadLetterRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoDeadLetterService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegDeadLetterRepository $repository,
    ) {}

    public function enqueue(Empresa $empresa, ?IntegConector $conector, string $evento, array $payload, string $erro): IntegDeadLetter
    {
        $dl = new IntegDeadLetter();
        $dl->setEmpresa($empresa)
           ->setConector($conector)
           ->setEvento($evento)
           ->setPayload($payload)
           ->setErroMensagem($erro)
           ->setProximaRetryEm(new \DateTimeImmutable('+1 hour'));
        $this->em->persist($dl);
        $this->em->flush();

        return $dl;
    }

    public function retry(IntegDeadLetter $dl): void
    {
        $dl->setTentativas($dl->getTentativas() + 1)
           ->setStatus(IntegDeadLetter::STATUS_RETRY)
           ->setProximaRetryEm(null)
           ->touch();

        $ok = random_int(1, 100) <= 70;
        if ($ok) {
            $dl->setStatus(IntegDeadLetter::STATUS_RESOLVIDO);
        } else {
            $dl->setStatus(IntegDeadLetter::STATUS_PENDENTE)
               ->setErroMensagem($dl->getErroMensagem() . ' (retry #' . $dl->getTentativas() . ' failed)')
               ->setProximaRetryEm(new \DateTimeImmutable('+' . (2 ** min($dl->getTentativas(), 10)) . ' hours'));
        }
        $this->em->flush();
    }

    public function discard(IntegDeadLetter $dl): void
    {
        $dl->setStatus(IntegDeadLetter::STATUS_DESCARTADO)->touch();
        $this->em->flush();
    }

    public function findForEmpresaById(Empresa $empresa, int $id): ?IntegDeadLetter
    {
        return $this->repository->findForEmpresaById($empresa, $id);
    }

    /** @return list<array<string, mixed>> */
    public function list(Empresa $empresa): array
    {
        return array_map(fn ($dl) => $dl->toArray(), $this->repository->findForEmpresa($empresa));
    }

    /** @return array{total: int, pendente: int, resolvido: int} */
    public function stats(Empresa $empresa): array
    {
        $items = $this->repository->findForEmpresa($empresa);

        return [
            'total' => count($items),
            'pendente' => count(array_filter($items, fn ($i) => $i->getStatus() === IntegDeadLetter::STATUS_PENDENTE)),
            'resolvido' => count(array_filter($items, fn ($i) => $i->getStatus() === IntegDeadLetter::STATUS_RESOLVIDO)),
        ];
    }

    public function seedDemoData(Empresa $empresa, ?IntegConector $conector = null): void
    {
        if ($this->repository->countForEmpresa($empresa) > 0) {
            return;
        }

        $events = [
            ['rh.admissao.concluida', ['usuario_id' => 1234, 'nome' => 'João Silva'], 'Timeout ao conectar AD: connection refused após 30s'],
            ['rh.folha.fechamento', ['periodo' => '2026-05', 'registros' => 450], 'TOTVS API retornou 503: Service Unavailable'],
            ['rh.esocial.evento', ['tipo' => 'S-2200', 'cpf' => '***.***.***-12'], 'Falha de schema: campo "dtNascto" não encontrado'],
        ];

        foreach ($events as [$ev, $payload, $erro]) {
            $dl = new IntegDeadLetter();
            $dl->setEmpresa($empresa)
               ->setConector($conector)
               ->setEvento($ev)
               ->setPayload($payload)
               ->setErroMensagem($erro)
               ->setProximaRetryEm(new \DateTimeImmutable('+2 hours'));
            $this->em->persist($dl);
        }
        $this->em->flush();
    }
}
