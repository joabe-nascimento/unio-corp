<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhFolhaHoleriteRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhPortalService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FuncionarioRepository $funcionarioRepo,
        private RhFolhaHoleriteRepository $holeriteRepo,
        private RhAuditService $audit,
    ) {}

    public function resolveFuncionarioForUser(Empresa $empresa, User $user): ?Funcionario
    {
        return $this->funcionarioRepo->findOneByUser($empresa, $user);
    }

    /** @return list<\App\Entity\RhFolhaHolerite> */
    public function listHolerites(Funcionario $funcionario): array
    {
        return $this->holeriteRepo->findByFuncionario($funcionario);
    }

    /**
     * @param array{telefone?: string, cargo?: string} $data
     */
    public function updateProfile(Funcionario $funcionario, array $data, ?User $actor = null): Funcionario
    {
        if (isset($data['telefone'])) {
            $funcionario->setTelefone($data['telefone'] !== '' ? $data['telefone'] : null);
        }
        if (isset($data['cargo'])) {
            $funcionario->setCargo($data['cargo'] !== '' ? $data['cargo'] : null);
        }

        $this->em->flush();

        $empresa = $funcionario->getEmpresa();
        if ($empresa) {
            $this->audit->log($empresa, $actor, 'portal', 'atualizar_perfil', 'funcionario', $funcionario->getId());
        }

        return $funcionario;
    }

    public function requireFuncionarioForUser(Empresa $empresa, User $user): Funcionario
    {
        $func = $this->resolveFuncionarioForUser($empresa, $user);
        if (!$func) {
            throw new RhProcessException('Nenhum cadastro de colaborador vinculado ao seu usuário.');
        }

        return $func;
    }
}
