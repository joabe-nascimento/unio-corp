<?php

namespace App\Service\Integracoes;

use App\Config\IntegracaoCatalogRegistry;
use App\Entity\Empresa;
use App\Entity\IntegPlaybookRun;
use App\Entity\User;
use App\Repository\IntegPlaybookRunRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoPlaybookRunService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegPlaybookRunRepository $repository,
    ) {}

    public function iniciar(Empresa $empresa, string $playbookId, User $user): IntegPlaybookRun
    {
        $playbook = null;
        foreach (IntegracaoCatalogRegistry::playbooks() as $pb) {
            if ($pb['id'] === $playbookId) {
                $playbook = $pb;
                break;
            }
        }
        if (!$playbook) {
            throw new \InvalidArgumentException('Playbook não encontrado.');
        }

        $passos = $playbook['passos'] ?? [];
        $steps = array_map(fn ($step, $i) => [
            'index' => $i + 1,
            'titulo' => is_string($step) ? $step : ($step['titulo'] ?? 'Passo ' . ($i + 1)),
            'descricao' => is_string($step) ? '' : ($step['descricao'] ?? ''),
            'feito' => false,
            'evidencia' => null,
            'feito_em' => null,
        ], $passos, range(0, count($passos) - 1));

        $run = new IntegPlaybookRun();
        $run->setEmpresa($empresa)
            ->setIniciadoPor($user)
            ->setPlaybookId($playbookId)
            ->setTitulo($playbook['titulo'])
            ->setSteps($steps);

        $this->em->persist($run);
        $this->em->flush();

        return $run;
    }

    public function markStep(IntegPlaybookRun $run, int $stepIndex, bool $done, ?string $evidencia = null): void
    {
        $steps = $run->getSteps();
        foreach ($steps as &$step) {
            if ($step['index'] === $stepIndex) {
                $step['feito'] = $done;
                $step['evidencia'] = $evidencia;
                $step['feito_em'] = $done ? (new \DateTimeImmutable())->format('d/m H:i') : null;
            }
        }
        unset($step);
        $run->setSteps($steps);

        $allDone = count($steps) > 0 && count(array_filter($steps, fn ($s) => !($s['feito'] ?? false))) === 0;
        if ($allDone) {
            $run->setStatus(IntegPlaybookRun::STATUS_CONCLUIDO)
                ->setConcluidoEm(new \DateTimeImmutable());
        }

        $this->em->flush();
    }

    public function load(Empresa $empresa, int $id): IntegPlaybookRun
    {
        $run = $this->repository->find($id);
        if (!$run || $run->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Run não encontrado.');
        }

        return $run;
    }

    /** @return list<array<string, mixed>> */
    public function list(Empresa $empresa): array
    {
        return array_map(fn ($r) => $r->toArray(), $this->repository->findForEmpresa($empresa));
    }
}
