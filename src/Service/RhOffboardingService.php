<?php

namespace App\Service;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhOffboardingProcess;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhOffboardingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhOffboardingProcessRepository $repo,
        private FuncionarioRepository $funcionarioRepo,
        private UserRepository $userRepo,
    ) {}

    public function create(
        Empresa $empresa,
        Funcionario $funcionario,
        ?\DateTimeImmutable $dataPrevista,
        ?string $motivo,
        ?string $observacoes,
    ): RhOffboardingProcess {
        if ($funcionario->getStatus() !== 'ATIVO') {
            throw new RhProcessException('Somente funcionários ativos podem iniciar um offboarding.');
        }

        if ($this->repo->hasOpenProcessForFuncionario($funcionario)) {
            throw new RhProcessException(
                'Já existe um processo de offboarding em andamento para este colaborador. Conclua ou cancele o processo anterior antes de abrir outro.'
            );
        }

        $process = new RhOffboardingProcess();
        $process->setEmpresa($empresa);
        $process->setFuncionario($funcionario);
        $process->setDataPrevista($dataPrevista);
        $process->setMotivo($motivo);
        $process->setObservacoes($observacoes);
        $process->setChecklist(RhOffboardingProcess::defaultChecklist());
        $process->setStatus(RhOffboardingProcess::STATUS_EM_ANDAMENTO);

        $this->em->persist($process);
        $this->em->flush();

        return $process;
    }

    public function cancel(RhOffboardingProcess $process): void
    {
        if ($process->getStatus() === RhOffboardingProcess::STATUS_CONCLUIDO) {
            throw new RhProcessException('Não é possível cancelar um offboarding já concluído.');
        }

        $process->setStatus(RhOffboardingProcess::STATUS_CANCELADO);
        $process->touch();
        $this->em->flush();
    }

    public function toggleChecklistItem(RhOffboardingProcess $process, string $itemId, bool $done): void
    {
        if (\in_array($process->getStatus(), [RhOffboardingProcess::STATUS_CONCLUIDO, RhOffboardingProcess::STATUS_CANCELADO], true)) {
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

    public function complete(RhOffboardingProcess $process): void
    {
        if ($process->getStatus() === RhOffboardingProcess::STATUS_CONCLUIDO) {
            throw new RhProcessException('Este processo de offboarding já foi concluído.');
        }

        if (!$process->isChecklistComplete()) {
            throw new RhProcessException('Marque todos os itens do checklist antes de concluir o offboarding.');
        }

        $funcionario = $process->getFuncionario();
        if ($funcionario->getStatus() !== 'ATIVO') {
            throw new RhProcessException('Este funcionário já está inativo.');
        }

        $funcionario->setStatus('INATIVO');
        $funcionario->setDataDemissao(DateNormalizer::immutableOrToday($process->getDataPrevista()));

        $user = $funcionario->getUser();
        if (!$user && $funcionario->getEmail()) {
            $user = $this->userRepo->findOneBy(['email' => mb_strtolower(trim($funcionario->getEmail()))]);
        }
        if ($user) {
            $user->setAtivo(false);
            $funcionario->setUser($user);
        }

        $process->setStatus(RhOffboardingProcess::STATUS_CONCLUIDO);
        $process->setDataConclusao(DateNormalizer::today());
        $process->touch();

        $this->em->flush();
    }

    /** @return list<RhOffboardingProcess> */
    public function listForEmpresa(Empresa $empresa, ?string $q = null, ?string $status = null): array
    {
        return $this->repo->findByEmpresa($empresa, $q, $status);
    }

    /** @return list<Funcionario> */
    public function listActiveFuncionarios(Empresa $empresa): array
    {
        return $this->funcionarioRepo->findBy(
            ['empresa' => $empresa, 'status' => 'ATIVO'],
            ['nome' => 'ASC']
        );
    }

    /** Funcionários ativos sem offboarding em aberto (para o formulário de nova demissão). */
    public function listActiveFuncionariosForOffboarding(Empresa $empresa): array
    {
        return array_values(array_filter(
            $this->listActiveFuncionarios($empresa),
            fn (Funcionario $f): bool => !$this->repo->hasOpenProcessForFuncionario($f),
        ));
    }

    public function countOpen(Empresa $empresa): int
    {
        return $this->repo->countOpenByEmpresa($empresa);
    }
}
