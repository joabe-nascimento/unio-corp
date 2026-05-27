<?php

namespace App\Service;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhOnboardingProcess;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOnboardingProcessRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhOnboardingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhOnboardingProcessRepository $repo,
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    public function create(Empresa $empresa, string $nome, string $email, ?string $cargo, ?\DateTimeImmutable $dataPrevista, ?string $observacoes): RhOnboardingProcess
    {
        $this->assertEmailAvailableForOnboarding($empresa, $email);

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

    public function update(RhOnboardingProcess $process, string $nome, string $email, ?string $cargo, ?\DateTimeImmutable $dataPrevista, ?string $observacoes): void
    {
        if ($process->getStatus() === RhOnboardingProcess::STATUS_CONCLUIDO) {
            throw new RhProcessException('Não é possível editar um onboarding concluído.');
        }
        if ($process->getStatus() === RhOnboardingProcess::STATUS_CANCELADO) {
            throw new RhProcessException('Não é possível editar um onboarding cancelado.');
        }

        $this->assertEmailAvailableForOnboarding($process->getEmpresa(), $email);

        $process->setNome($nome);
        $process->setEmail($email);
        $process->setCargo($cargo);
        $process->setDataPrevista($dataPrevista);
        $process->setObservacoes($observacoes);
        $process->touch();
        $this->em->flush();
    }

    public function cancel(RhOnboardingProcess $process): void
    {
        if ($process->getStatus() === RhOnboardingProcess::STATUS_CONCLUIDO) {
            throw new RhProcessException('Não é possível cancelar um onboarding já concluído.');
        }

        $process->setStatus(RhOnboardingProcess::STATUS_CANCELADO);
        $process->touch();
        $this->em->flush();
    }

    public function toggleChecklistItem(RhOnboardingProcess $process, string $itemId, bool $done): void
    {
        if (\in_array($process->getStatus(), [RhOnboardingProcess::STATUS_CONCLUIDO, RhOnboardingProcess::STATUS_CANCELADO], true)) {
            return;
        }

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
        if ($process->getStatus() === RhOnboardingProcess::STATUS_CONCLUIDO) {
            throw new RhProcessException('Este processo de onboarding já foi concluído.');
        }
        if ($process->getStatus() === RhOnboardingProcess::STATUS_CANCELADO) {
            throw new RhProcessException('Este processo de onboarding foi cancelado.');
        }

        if (!$process->isChecklistComplete()) {
            throw new RhProcessException('Marque todos os itens do checklist antes de concluir o onboarding.');
        }

        $this->assertEmailAvailableForOnboarding($process->getEmpresa(), $process->getEmail());

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
    public function listForEmpresa(Empresa $empresa, ?string $q = null, ?string $status = null): array
    {
        return $this->repo->findByEmpresa($empresa, $q, $status);
    }

    public function countOpen(Empresa $empresa): int
    {
        return $this->repo->countOpenByEmpresa($empresa);
    }

    private function assertEmailAvailableForOnboarding(Empresa $empresa, string $email): void
    {
        if ($this->funcionarioRepo->existsByEmail($empresa, $email)) {
            throw new RhProcessException(
                'Já existe um funcionário nesta empresa com este e-mail. Use outro e-mail ou localize o cadastro em Funcionários.'
            );
        }
    }
}
