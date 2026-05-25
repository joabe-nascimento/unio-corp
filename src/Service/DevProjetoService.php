<?php

namespace App\Service;

use App\Doctrine\DateNormalizer;
use App\Entity\DevMeta;
use App\Entity\DevProjeto;
use App\Entity\DevTarefa;
use App\Entity\Empresa;
use App\Repository\DevMetaRepository;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;
use Doctrine\ORM\EntityManagerInterface;

class DevProjetoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DevProjetoRepository $projetoRepo,
        private DevMetaRepository $metaRepo,
        private DevTarefaRepository $tarefaRepo,
    ) {}

    public function createProjeto(
        Empresa $empresa,
        string $nome,
        ?string $codigo,
        ?string $descricao,
        ?string $area,
        string $status,
        ?string $cor,
        ?\DateTimeImmutable $dataAlvo,
    ): DevProjeto {
        $p = new DevProjeto();
        $p->setEmpresa($empresa);
        $p->setNome($nome);
        $p->setCodigo($codigo);
        $p->setDescricao($descricao);
        $p->setArea($area);
        $p->setStatus($status);
        $p->setCor($cor ?: '#4F7FFF');
        $p->setDataAlvo($dataAlvo);
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    public function createMeta(
        Empresa $empresa,
        ?DevProjeto $projeto,
        string $titulo,
        ?string $descricao,
        string $status,
        string $prioridade,
        int $progresso,
        ?\DateTimeImmutable $dataAlvo,
    ): DevMeta {
        $m = new DevMeta();
        $m->setEmpresa($empresa);
        $m->setProjeto($projeto);
        $m->setTitulo($titulo);
        $m->setDescricao($descricao);
        $m->setStatus($status);
        $m->setPrioridade($prioridade);
        $m->setProgressoPercent($progresso);
        $m->setDataAlvo($dataAlvo);
        $this->em->persist($m);
        $this->em->flush();

        return $m;
    }

    public function createTarefa(
        DevProjeto $projeto,
        string $titulo,
        ?string $descricao,
        string $status,
        string $prioridade,
        ?DevMeta $meta = null,
    ): DevTarefa {
        $t = new DevTarefa();
        $t->setEmpresa($projeto->getEmpresa());
        $t->setProjeto($projeto);
        $t->setMeta($meta);
        $t->setTitulo($titulo);
        $t->setDescricao($descricao);
        $t->setStatus($status);
        $t->setPrioridade($prioridade);
        $t->setOrdem(count($this->tarefaRepo->findBy(['projeto' => $projeto, 'status' => $status])));
        $this->em->persist($t);
        $this->recalculateProgress($projeto);
        $this->em->flush();

        return $t;
    }

    public function updateTarefa(
        DevTarefa $tarefa,
        DevProjeto $projeto,
        string $titulo,
        ?string $descricao,
        string $status,
        string $prioridade,
        ?DevMeta $meta = null,
    ): DevTarefa {
        $oldProjeto = $tarefa->getProjeto();
        $tarefa->setProjeto($projeto);
        $tarefa->setEmpresa($projeto->getEmpresa());
        $tarefa->setMeta($meta);
        $tarefa->setTitulo($titulo);
        $tarefa->setDescricao($descricao);
        $tarefa->setStatus($status);
        $tarefa->setPrioridade($prioridade);
        $tarefa->touch();
        $this->em->flush();
        $this->recalculateProgress($projeto);
        if ($oldProjeto->getId() !== $projeto->getId()) {
            $this->recalculateProgress($oldProjeto);
        }

        return $tarefa;
    }

    public function deleteTarefa(DevTarefa $tarefa): void
    {
        $projeto = $tarefa->getProjeto();
        $this->em->remove($tarefa);
        $this->em->flush();
        $this->recalculateProgress($projeto);
    }

    public function moveTarefa(DevTarefa $tarefa, string $status, int $ordem): bool
    {
        if (!isset(DevTarefa::KANBAN_COLUMNS[$status])) {
            return false;
        }
        $tarefa->setStatus($status);
        $tarefa->setOrdem($ordem);
        $tarefa->touch();
        $this->recalculateProgress($tarefa->getProjeto());
        $this->em->flush();

        return true;
    }

    public function recalculateProgress(DevProjeto $projeto): void
    {
        $tarefas = $this->tarefaRepo->findByEmpresa($projeto->getEmpresa(), $projeto->getId());
        if ($tarefas === []) {
            $projeto->setProgresso(0);
            $projeto->touch();

            return;
        }
        $done = count(array_filter($tarefas, static fn (DevTarefa $t) => $t->getStatus() === DevTarefa::STATUS_CONCLUIDO));
        $projeto->setProgresso((int) round(($done / count($tarefas)) * 100));
        $projeto->touch();
    }

    /** @return list<DevProjeto> */
    public function listProjetos(Empresa $empresa): array
    {
        return $this->projetoRepo->findByEmpresa($empresa);
    }

    /** @return list<DevMeta> */
    public function listMetas(Empresa $empresa): array
    {
        return $this->metaRepo->findByEmpresa($empresa);
    }

    /** @return array<string, list<DevTarefa>> */
    public function kanban(Empresa $empresa, ?int $projetoId = null): array
    {
        return $this->tarefaRepo->groupByStatus($empresa, $projetoId);
    }

    public function parseDate(mixed $value): ?\DateTimeImmutable
    {
        return DateNormalizer::fromFormDate($value);
    }

    public function countProjetosAtivos(Empresa $empresa): int
    {
        return $this->projetoRepo->countEmAndamento($empresa);
    }
}
