<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhOffboardingProcess;
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
        ?\DateTimeInterface $dataPrevista,
        ?string $motivo,
        ?string $observacoes,
    ): RhOffboardingProcess {
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

    public function toggleChecklistItem(RhOffboardingProcess $process, string $itemId, bool $done): void
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

    public function complete(RhOffboardingProcess $process): void
    {
        $funcionario = $process->getFuncionario();
        $funcionario->setStatus('INATIVO');
        $funcionario->setDataDemissao($this->asDate($process->getDataPrevista()));

        $user = $funcionario->getUser();
        if (!$user && $funcionario->getEmail()) {
            $user = $this->userRepo->findOneBy(['email' => mb_strtolower(trim($funcionario->getEmail()))]);
        }
        if ($user) {
            $user->setAtivo(false);
            $funcionario->setUser($user);
        }

        $process->setStatus(RhOffboardingProcess::STATUS_CONCLUIDO);
        $process->setDataConclusao(new \DateTime('today'));
        $process->touch();

        $this->em->flush();
    }

    /** @return list<RhOffboardingProcess> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findByEmpresa($empresa);
    }

    /** @return list<Funcionario> */
    public function listActiveFuncionarios(Empresa $empresa): array
    {
        return $this->funcionarioRepo->findBy(
            ['empresa' => $empresa, 'status' => 'ATIVO'],
            ['nome' => 'ASC']
        );
    }

    public function countOpen(Empresa $empresa): int
    {
        return $this->repo->countOpenByEmpresa($empresa);
    }

    private function asDate(?\DateTimeInterface $value): \DateTime
    {
        if ($value === null) {
            return new \DateTime('today');
        }

        return $value instanceof \DateTime ? $value : \DateTime::createFromInterface($value);
    }
}
