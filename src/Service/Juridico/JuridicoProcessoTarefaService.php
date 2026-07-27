<?php

namespace App\Service\Juridico;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoProcessoTarefa;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoProcessoTarefaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class JuridicoProcessoTarefaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoProcessoTarefaRepository $repo,
        private UserRepository $userRepo,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(JuridicoProcesso $processo, array $data): JuridicoProcessoTarefa
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            throw new JuridicoProcessException('Informe o título da tarefa.');
        }

        $tarefa = new JuridicoProcessoTarefa();
        $tarefa->setProcesso($processo);
        $tarefa->setTitulo($titulo);
        $tarefa->setDescricao($this->nullIfEmpty($data['descricao'] ?? null));
        $tarefa->setPrazo(DateNormalizer::fromFormDate($data['prazo'] ?? null));

        $responsavelId = (int) ($data['responsavel_id'] ?? 0);
        if ($responsavelId > 0) {
            $tarefa->setResponsavel($this->userRepo->findOneBy(['id' => $responsavelId, 'empresa' => $processo->getEmpresa()]));
        }

        $this->em->persist($tarefa);
        $this->em->flush();

        return $tarefa;
    }

    public function loadForProcesso(JuridicoProcesso $processo, int $id): JuridicoProcessoTarefa
    {
        $tarefa = $this->repo->findOneByProcesso($processo, $id);
        if (!$tarefa) {
            throw new JuridicoProcessException('Tarefa não encontrada.');
        }

        return $tarefa;
    }

    public function alternarConclusao(JuridicoProcessoTarefa $tarefa): void
    {
        if ($tarefa->isConcluida()) {
            $tarefa->reabrir();
        } else {
            $tarefa->marcarConcluida();
        }
        $this->em->flush();
    }

    public function delete(JuridicoProcessoTarefa $tarefa): void
    {
        $this->em->remove($tarefa);
        $this->em->flush();
    }

    /** @return list<JuridicoProcessoTarefa> */
    public function findForProcesso(JuridicoProcesso $processo): array
    {
        return $this->repo->findForProcesso($processo);
    }

    /** @return list<JuridicoProcessoTarefa> */
    public function findPendentesForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findPendentesForEmpresa($empresa);
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
