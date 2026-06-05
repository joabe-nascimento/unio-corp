<?php

namespace App\Service\Integracoes;

use App\Entity\Empresa;
use App\Entity\IntegWebhook;
use App\Repository\IntegConectorRepository;
use App\Repository\IntegWebhookRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoWebhookService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegWebhookRepository $repo,
        private IntegConectorRepository $conectorRepo,
        private IntegracaoLogService $logs,
    ) {}

    public function loadForEmpresa(Empresa $empresa, int $id): IntegWebhook
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if ($item === null) {
            throw new \InvalidArgumentException('Webhook não encontrado.');
        }

        return $item;
    }

    /** @return list<array<string, mixed>> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return array_map(static fn (IntegWebhook $w) => $w->toArray(), $this->repo->findForEmpresa($empresa));
    }

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): IntegWebhook
    {
        $webhook = (new IntegWebhook())->setEmpresa($empresa);
        $this->applyForm($empresa, $webhook, $data);
        $this->em->persist($webhook);
        $this->logs->info($empresa, 'Webhook criado: ' . $webhook->getEvento(), $webhook->getNome(), $webhook->getConector());
        $this->em->flush();

        return $webhook;
    }

    /** @param array<string, mixed> $data */
    public function update(Empresa $empresa, IntegWebhook $webhook, array $data): void
    {
        $this->applyForm($empresa, $webhook, $data);
        $this->em->flush();
    }

    public function delete(IntegWebhook $webhook): void
    {
        $this->em->remove($webhook);
        $this->em->flush();
    }

    public function toggle(IntegWebhook $webhook): void
    {
        $webhook->setAtivo(!$webhook->isAtivo());
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    private function applyForm(Empresa $empresa, IntegWebhook $webhook, array $data): void
    {
        $webhook
            ->setNome($this->requireString($data, 'nome', 'Nome'))
            ->setEvento($this->requireString($data, 'evento', 'Evento'))
            ->setUrl($this->requireString($data, 'url', 'URL'));

        $direcao = (string) ($data['direcao'] ?? IntegWebhook::DIR_OUT);
        if (!\in_array($direcao, [IntegWebhook::DIR_IN, IntegWebhook::DIR_OUT], true)) {
            throw new \InvalidArgumentException('Direção inválida.');
        }
        $webhook->setDirecao($direcao);

        $conectorId = (int) ($data['conector_id'] ?? 0);
        if ($conectorId > 0) {
            $conector = $this->conectorRepo->findOneForEmpresa($empresa, $conectorId);
            if ($conector === null) {
                throw new \InvalidArgumentException('Conector vinculado não encontrado.');
            }
            $webhook->setConector($conector);
        } else {
            $webhook->setConector(null);
        }

        if (isset($data['ativo'])) {
            $webhook->setAtivo((bool) $data['ativo']);
        }
    }

    /** @param array<string, mixed> $data */
    private function requireString(array $data, string $key, string $label): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException($label . ' é obrigatório.');
        }

        return $value;
    }
}
