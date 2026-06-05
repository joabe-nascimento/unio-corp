<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiPlaybook;
use App\Repository\TiPlaybookRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TiPlaybookService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiPlaybookRepository $repository,
    ) {}

    public function ensureInitialized(Empresa $empresa): void
    {
        if ($this->repository->findActiveByEmpresa($empresa) !== []) {
            return;
        }

        $defaults = [
            ['titulo' => 'P1 — Indisponibilidade de rede', 'gatilho' => 'P1 rede', 'categoria' => 'rede', 'prioridade' => 'P1', 'passos' => ['Isolar escopo do incidente', 'Acionar NOC secundário', 'Comunicar stakeholders', 'Executar rollback se necessário']],
            ['titulo' => 'VPN — reconexão pós-patch', 'gatilho' => 'vpn', 'categoria' => 'rede', 'prioridade' => 'P2', 'passos' => ['Validar certificado MFA', 'Limpar cache VPN', 'Testar rota alternativa', 'Registrar workaround no KB']],
            ['titulo' => 'Reset de senha AD', 'gatilho' => 'senha', 'categoria' => 'acesso', 'prioridade' => 'P3', 'passos' => ['Confirmar identidade', 'Desbloquear conta AD', 'Forçar troca no próximo login', 'Orientar self-service']],
        ];

        foreach ($defaults as $row) {
            $pb = new TiPlaybook();
            $pb->setEmpresa($empresa)
                ->setTitulo($row['titulo'])
                ->setGatilho($row['gatilho'])
                ->setCategoria($row['categoria'])
                ->setPrioridade($row['prioridade'])
                ->setPassos($row['passos']);
            $this->em->persist($pb);
        }
        $this->em->flush();
    }

    /** @return list<array<string, mixed>> */
    public function list(Empresa $empresa): array
    {
        $this->ensureInitialized($empresa);

        return array_map(static fn (TiPlaybook $p) => $p->toArray(), $this->repository->findActiveByEmpresa($empresa));
    }

    /** @return array<string, mixed>|null */
    public function matchForTicket(array $ticket, Empresa $empresa): ?array
    {
        $this->ensureInitialized($empresa);
        $text = mb_strtolower(($ticket['title'] ?? '') . ' ' . ($ticket['summary'] ?? '') . ' ' . ($ticket['priority'] ?? ''));

        foreach ($this->repository->findActiveByEmpresa($empresa) as $playbook) {
            if ($playbook->getPrioridade() && ($ticket['priority'] ?? '') === $playbook->getPrioridade()) {
                return $playbook->toArray();
            }
            if ($playbook->getCategoria() && ($ticket['category'] ?? '') === $playbook->getCategoria()) {
                return $playbook->toArray();
            }
            if (str_contains($text, mb_strtolower($playbook->getGatilho()))) {
                return $playbook->toArray();
            }
        }

        return null;
    }

    /**
     * Returns pre-initialized playbook steps for a ticket (from matched playbook).
     *
     * @param array<string, mixed> $ticket
     * @return list<array{step: int, titulo: string, feito: bool, evidencia: string|null, feito_em: string|null}>
     */
    public function initPlaybookSteps(array $ticket, Empresa $empresa): array
    {
        $playbook = $this->matchForTicket($ticket, $empresa);
        if ($playbook === null) {
            return [];
        }

        $steps = [];
        foreach (($playbook['steps'] ?? []) as $i => $stepText) {
            $steps[] = [
                'step' => $i + 1,
                'titulo' => \is_string($stepText) ? $stepText : (string) ($stepText['titulo'] ?? ''),
                'feito' => false,
                'evidencia' => null,
                'feito_em' => null,
            ];
        }

        return $steps;
    }
}
