<?php

namespace App\Service;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhOnboardingProcess;
use App\Repository\RhOnboardingProcessRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhOnboardingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhOnboardingProcessRepository $repo,
    ) {}

    public function create(Empresa $empresa, string $nome, string $email, ?string $cargo, ?\DateTimeImmutable $dataPrevista, ?string $observacoes): RhOnboardingProcess
    {
        $process = new RhOnboardingProcess();
        $process->setEmpresa($empresa);
        $process->setNome($nome);
        $process->setEmail($email);
        $process->setCargo($cargo);
        $process->setDataPrevista($dataPrevista);
        $process->setObservacoes($observacoes);
        $process->setChecklist(RhOnboardingProcess::defaultChecklist());
        $process->setStatus(RhOnboardingProcess::STATUS_EM_ANDAMENTO);

        $this->em->persist($process);
        $this->em->flush();

        return $process;
    }

    public function toggleChecklistItem(RhOnboardingProcess $process, string $itemId, bool $done): void
    {
        $checklist = $process->getChecklist();
        foreach ($checklist as &$item) {
            if (($item['id'] ?? '') === $itemId) {
                $item['done'] = $done;
            }
        }
        unset($item);
        $process->setChecklist($checklist);
        $process->touch();
        $this->em->flush();
    }

    public function complete(RhOnboardingProcess $process): Funcionario
    {
        $funcionario = new Funcionario();
        $funcionario->setEmpresa($process->getEmpresa());
        $funcionario->setNome($process->getNome());
        $funcionario->setEmail($process->getEmail());
        $funcionario->setCargo($process->getCargo());
        $funcionario->setDataAdmissao(DateNormalizer::immutableOrToday($process->getDataPrevista()));
        $funcionario->setStatus('ATIVO');

        $this->em->persist($funcionario);

        $process->setFuncionario($funcionario);
        $process->setStatus(RhOnboardingProcess::STATUS_CONCLUIDO);
        $process->setDataConclusao(DateNormalizer::today());
        $process->touch();

        $this->em->flush();

        return $funcionario;
    }

    /** @return list<RhOnboardingProcess> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findByEmpresa($empresa);
    }

    public function countOpen(Empresa $empresa): int
    {
        return $this->repo->countOpenByEmpresa($empresa);
    }

}
