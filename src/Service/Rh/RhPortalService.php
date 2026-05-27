<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhComunicado;
use App\Entity\RhFolhaHolerite;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhComunicadoLeituraRepository;
use App\Repository\RhComunicadoRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhFolhaHoleriteRepository;
use App\Service\RhFeriasService;
use Doctrine\ORM\EntityManagerInterface;

class RhPortalService
{
    public function __construct(
        private EntityManagerInterface $em,
        private FuncionarioRepository $funcionarioRepo,
        private RhFolhaHoleriteRepository $holeriteRepo,
        private RhFeriasRepository $feriasRepo,
        private RhComunicadoRepository $comunicadoRepo,
        private RhComunicadoLeituraRepository $leituraRepo,
        private RhFeriasService $feriasService,
        private RhComunicacaoService $comunicacao,
        private RhAuditService $audit,
    ) {}

    /**
     * Resolve colaborador pelo vínculo user_id ou e-mail (auto-vincula se seguro).
     */
    public function resolveFuncionarioForUser(Empresa $empresa, User $user): ?Funcionario
    {
        $func = $this->funcionarioRepo->findOneByUser($empresa, $user);
        if ($func) {
            return $func;
        }

        $func = $this->funcionarioRepo->findOneByEmail($empresa, (string) $user->getEmail());
        if ($func && $func->getUser() === null) {
            $func->setUser($user);
            $this->em->flush();
            $this->audit->log($empresa, $user, 'portal', 'vincular_usuario', 'funcionario', $func->getId());
        }

        return $func;
    }

    public function requireFuncionarioForUser(Empresa $empresa, User $user): Funcionario
    {
        $func = $this->resolveFuncionarioForUser($empresa, $user);
        if (!$func) {
            throw new RhProcessException(
                'Nenhum cadastro de colaborador vinculado ao seu usuário. Peça ao RH para vincular seu e-mail ou conta.'
            );
        }

        return $func;
    }

    /**
     * @return array{
     *   ferias_pendentes: int,
     *   ferias_proximas: ?\App\Entity\RhFerias,
     *   holerites_count: int,
     *   comunicados_nao_lidos: int
     * }
     */
    public function dashboardSummary(Funcionario $funcionario): array
    {
        $ferias = $this->feriasRepo->findByFuncionario($funcionario, 50);
        $pendentes = 0;
        $proxima = null;

        foreach ($ferias as $f) {
            if ($f->getStatus() === 'SOLICITADA') {
                ++$pendentes;
            }
            if (\in_array($f->getStatus(), ['APROVADA', 'EM_GOZO'], true) && $f->getDataInicio() >= new \DateTimeImmutable('today')) {
                if ($proxima === null || $f->getDataInicio() < $proxima->getDataInicio()) {
                    $proxima = $f;
                }
            }
        }

        $holerites = $this->holeriteRepo->findByFuncionario($funcionario, 100);
        $naoLidos = 0;
        foreach ($this->comunicadoRepo->findAtivosForEmpresa($funcionario->getEmpresa()) as $com) {
            if (!$this->leituraRepo->findOneByComunicadoAndFuncionario($com, $funcionario)) {
                ++$naoLidos;
            }
        }

        return [
            'ferias_pendentes' => $pendentes,
            'ferias_proximas' => $proxima,
            'holerites_count' => \count($holerites),
            'comunicados_nao_lidos' => $naoLidos,
        ];
    }

    /** @return list<\App\Entity\RhFolhaHolerite> */
    public function listHolerites(Funcionario $funcionario): array
    {
        return $this->holeriteRepo->findByFuncionario($funcionario);
    }

    public function getHoleriteForFuncionario(int $id, Funcionario $funcionario): RhFolhaHolerite
    {
        $holerite = $this->holeriteRepo->findOneForFuncionario($id, $funcionario);
        if (!$holerite) {
            throw new RhProcessException('Holerite não encontrado ou competência ainda não fechada.');
        }

        return $holerite;
    }

    /** @return list<\App\Entity\RhFerias> */
    public function listFerias(Funcionario $funcionario): array
    {
        return $this->feriasRepo->findByFuncionario($funcionario);
    }

    public function solicitarFerias(
        Empresa $empresa,
        Funcionario $funcionario,
        \DateTimeImmutable $inicio,
        \DateTimeImmutable $fim,
        ?string $observacoes,
        User $solicitante,
    ): \App\Entity\RhFerias {
        $ferias = $this->feriasService->solicitar($empresa, $funcionario, $inicio, $fim, $observacoes, $solicitante);

        $this->comunicacao->queueEmail(
            $empresa,
            (string) $funcionario->getEmail(),
            'Solicitação de férias registrada',
            'rh_ferias_solicitada',
            ['ferias_id' => $ferias->getId(), 'inicio' => $inicio->format('Y-m-d'), 'fim' => $fim->format('Y-m-d')]
        );

        $this->audit->log($empresa, $solicitante, 'portal', 'solicitar_ferias', 'rh_ferias', $ferias->getId());

        return $ferias;
    }

    /**
     * @return list<array{comunicado: RhComunicado, lido: bool}>
     */
    public function listComunicados(Funcionario $funcionario): array
    {
        $items = [];
        foreach ($this->comunicadoRepo->findAtivosForEmpresa($funcionario->getEmpresa()) as $com) {
            $items[] = [
                'comunicado' => $com,
                'lido' => $this->leituraRepo->findOneByComunicadoAndFuncionario($com, $funcionario) !== null,
            ];
        }

        return $items;
    }

    public function markComunicadoRead(RhComunicado $comunicado, Funcionario $funcionario, ?User $user): void
    {
        if ($comunicado->getEmpresa()?->getId() !== $funcionario->getEmpresa()?->getId()) {
            throw new RhProcessException('Comunicado inválido.');
        }
        $this->comunicacao->markRead($comunicado, $funcionario);
        $empresa = $funcionario->getEmpresa();
        if ($empresa) {
            $this->audit->log($empresa, $user, 'portal', 'ler_comunicado', 'rh_comunicado', $comunicado->getId());
        }
    }

    /**
     * @param array{telefone?: string} $data
     */
    public function updateProfile(Funcionario $funcionario, array $data, ?User $actor = null): Funcionario
    {
        if (isset($data['telefone'])) {
            $funcionario->setTelefone($data['telefone'] !== '' ? trim($data['telefone']) : null);
        }

        $this->em->flush();

        $empresa = $funcionario->getEmpresa();
        if ($empresa) {
            $this->audit->log($empresa, $actor, 'portal', 'atualizar_perfil', 'funcionario', $funcionario->getId());
        }

        return $funcionario;
    }
}
