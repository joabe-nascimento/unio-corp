<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\InovDecisao;
use App\Entity\InovIdeia;
use App\Entity\User;
use App\Repository\InovIdeiaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InovacaoIdeiaService
{
    private const STAGE_PREFIX = [
        InovIdeia::STAGE_IDEIA => 'I',
        InovIdeia::STAGE_HIPOTESE => 'H',
        InovIdeia::STAGE_POC => 'P',
        InovIdeia::STAGE_PILOTO => 'T',
        InovIdeia::STAGE_ESCALA => 'S',
    ];

    private const VALID_STAGES = [
        InovIdeia::STAGE_IDEIA,
        InovIdeia::STAGE_HIPOTESE,
        InovIdeia::STAGE_POC,
        InovIdeia::STAGE_PILOTO,
        InovIdeia::STAGE_ESCALA,
        InovIdeia::STAGE_KILL,
        InovIdeia::STAGE_ARQUIVADO,
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private InovIdeiaRepository $repo,
    ) {}

    public function loadForEmpresa(Empresa $empresa, int $id): InovIdeia
    {
        $ideia = $this->repo->findOneForEmpresa($empresa, $id);
        if (!$ideia) {
            throw new \InvalidArgumentException('Ideia não encontrada.');
        }

        return $ideia;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromForm(Empresa $empresa, User $autor, array $data): InovIdeia
    {
        $titulo = trim((string) ($data['titulo'] ?? $data['title'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('Título é obrigatório.');
        }

        $stage = $this->normalizeStage((string) ($data['estagio'] ?? $data['stage'] ?? InovIdeia::STAGE_IDEIA));

        $ideia = new InovIdeia();
        $ideia->setEmpresa($empresa);
        $ideia->setAutor($autor);
        $ideia->setCodigo($this->generateCodigo($empresa, $stage));
        $ideia->setTitulo($titulo);
        $ideia->setEstagio($stage);
        $this->applyFormData($ideia, $data);

        $this->em->persist($ideia);
        $this->em->flush();

        return $ideia;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFromForm(InovIdeia $ideia, array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? $data['title'] ?? $ideia->getTitulo()));
        if ($titulo === '') {
            throw new \InvalidArgumentException('Título é obrigatório.');
        }

        $ideia->setTitulo($titulo);
        $this->applyFormData($ideia, $data);
        $ideia->touch();
        $this->em->flush();
    }

    public function archive(InovIdeia $ideia): void
    {
        $ideia->setArquivado(true);
        $ideia->setEstagio(InovIdeia::STAGE_ARQUIVADO);
        $ideia->touch();
        $this->em->flush();
    }

    public function promoteToLab(InovIdeia $ideia): void
    {
        if ($ideia->getEstagio() !== InovIdeia::STAGE_IDEIA) {
            throw new \InvalidArgumentException('Somente ideias do backlog podem ser promovidas ao laboratório.');
        }

        $ideia->setEstagio(InovIdeia::STAGE_HIPOTESE);
        if ($ideia->getProgresso() < 10) {
            $ideia->setProgresso(10);
        }
        $ideia->touch();
        $this->em->flush();
    }

    public function moveStage(InovIdeia $ideia, string $stage): void
    {
        $stage = $this->normalizeStage($stage);
        if (!\in_array($stage, self::VALID_STAGES, true)) {
            throw new \InvalidArgumentException('Estágio inválido.');
        }

        $ideia->setEstagio($stage);
        $ideia->setArquivado($stage === InovIdeia::STAGE_ARQUIVADO);
        $ideia->touch();
        $this->em->flush();
    }

    public function vote(InovIdeia $ideia): void
    {
        $ideia->setVotos($ideia->getVotos() + 1);
        $ideia->touch();
        $this->em->flush();
    }

    public function registerDecision(
        InovIdeia $ideia,
        User $autor,
        string $tipo,
        string $motivo,
    ): InovDecisao {
        $tipo = strtolower(trim($tipo));
        if (!\in_array($tipo, [InovDecisao::TIPO_KILL, InovDecisao::TIPO_PIVOT, InovDecisao::TIPO_SCALE], true)) {
            throw new \InvalidArgumentException('Tipo de decisão inválido.');
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \InvalidArgumentException('Motivo da decisão é obrigatório.');
        }

        $decisao = new InovDecisao();
        $decisao->setEmpresa($ideia->getEmpresa());
        $decisao->setIdeia($ideia);
        $decisao->setAutor($autor);
        $decisao->setTitulo($ideia->getTitulo());
        $decisao->setTipo($tipo);
        $decisao->setMotivo($motivo);
        $decisao->setOwnerNome($ideia->getOwnerNome());

        match ($tipo) {
            InovDecisao::TIPO_KILL => $ideia->setEstagio(InovIdeia::STAGE_KILL),
            InovDecisao::TIPO_SCALE => $ideia->setEstagio(InovIdeia::STAGE_ESCALA),
            InovDecisao::TIPO_PIVOT => $ideia->setEstagio(InovIdeia::STAGE_HIPOTESE),
        };

        if ($tipo === InovDecisao::TIPO_SCALE) {
            $ideia->setProgresso(100);
        }

        $ideia->touch();
        $this->em->persist($decisao);
        $this->em->flush();

        return $decisao;
    }

    public function delete(InovIdeia $ideia): void
    {
        $this->em->remove($ideia);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    public function toPipelineArray(InovIdeia $ideia): array
    {
        $item = [
            'db_id' => $ideia->getId(),
            'id' => $ideia->getCodigo(),
            'title' => $ideia->getTitulo(),
            'summary' => $ideia->getResumo() ?? '',
            'owner' => $ideia->getOwnerNome() ?? '—',
            'tags' => $ideia->getTags(),
            'progress' => $ideia->getProgresso(),
            'stage' => $ideia->getEstagio(),
            'days' => $ideia->getDaysOpen(),
            'impact' => $ideia->getImpacto(),
            'effort' => $ideia->getEsforco(),
            'votes' => $ideia->getVotos(),
            'quadrant' => $ideia->getQuadrant(),
            'priority' => $this->priorityFromQuadrant($ideia->getQuadrant()),
        ];

        if ($ideia->getMetrica() !== null) {
            $item['metric'] = $ideia->getMetrica();
        }

        if ($ideia->getProblema() !== null) {
            $item['problem'] = $ideia->getProblema();
        }
        if ($ideia->getHipotese() !== null) {
            $item['hypothesis'] = $ideia->getHipotese();
        }
        if ($ideia->getMetodoTeste() !== null) {
            $item['test'] = $ideia->getMetodoTeste();
        }
        if ($ideia->getRigor() !== null) {
            $item['rigor'] = $ideia->getRigor();
        }

        return $item;
    }

    public function generateCodigo(Empresa $empresa, string $stage): string
    {
        $stage = $this->normalizeStage($stage);
        $prefix = self::STAGE_PREFIX[$stage] ?? 'X';
        $seq = $this->repo->countByCodigoPrefix($empresa, $prefix) + 1;

        return sprintf('%s%02d', $prefix, $seq);
    }

    /**
     * @param array<string, list<InovIdeia>> $pipeline
     * @return array<string, list<array<string, mixed>>>
     */
    public function pipelineFromEntities(array $pipeline): array
    {
        $result = [];
        foreach ($pipeline as $stage => $items) {
            $result[$stage] = array_map(fn (InovIdeia $i) => $this->toPipelineArray($i), $items);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFormData(InovIdeia $ideia, array $data): void
    {
        if (isset($data['resumo']) || isset($data['summary'])) {
            $ideia->setResumo($this->nullIfEmpty($data['resumo'] ?? $data['summary'] ?? null));
        }
        if (isset($data['problema'])) {
            $ideia->setProblema($this->nullIfEmpty($data['problema']));
        }
        if (isset($data['hipotese'])) {
            $ideia->setHipotese($this->nullIfEmpty($data['hipotese']));
        }
        if (isset($data['metrica_sucesso'])) {
            $ideia->setMetricaSucesso($this->nullIfEmpty($data['metrica_sucesso']));
        }
        if (isset($data['metodo_teste'])) {
            $ideia->setMetodoTeste($this->nullIfEmpty($data['metodo_teste']));
        }
        if (isset($data['metrica']) || isset($data['metric'])) {
            $ideia->setMetrica($this->nullIfEmpty($data['metrica'] ?? $data['metric'] ?? null));
        }
        if (isset($data['owner_nome']) || isset($data['owner'])) {
            $ideia->setOwnerNome($this->nullIfEmpty($data['owner_nome'] ?? $data['owner'] ?? null));
        }
        if (isset($data['hub_relacionado']) || isset($data['hub'])) {
            $ideia->setHubRelacionado($this->nullIfEmpty($data['hub_relacionado'] ?? $data['hub'] ?? null));
        }
        if (isset($data['categoria'])) {
            $ideia->setCategoria($this->nullIfEmpty($data['categoria']));
        }
        if (isset($data['urgencia'])) {
            $ideia->setUrgencia($this->nullIfEmpty($data['urgencia']));
        }
        if (isset($data['impacto']) || isset($data['impact'])) {
            $ideia->setImpacto((int) ($data['impacto'] ?? $data['impact'] ?? $ideia->getImpacto()));
        }
        if (isset($data['esforco']) || isset($data['effort'])) {
            $ideia->setEsforco((int) ($data['esforco'] ?? $data['effort'] ?? $ideia->getEsforco()));
        }
        if (isset($data['progresso']) || isset($data['progress'])) {
            $ideia->setProgresso((int) ($data['progresso'] ?? $data['progress'] ?? $ideia->getProgresso()));
        }
        if (isset($data['rigor'])) {
            $ideia->setRigor($data['rigor'] !== '' && $data['rigor'] !== null ? (int) $data['rigor'] : null);
        }
        if (isset($data['tags'])) {
            $tags = $data['tags'];
            if (\is_string($tags)) {
                $tags = array_map('trim', explode(',', $tags));
            }
            $ideia->setTags(\is_array($tags) ? $tags : []);
        }
        if (isset($data['estagio']) || isset($data['stage'])) {
            $ideia->setEstagio($this->normalizeStage((string) ($data['estagio'] ?? $data['stage'])));
        }
    }

    private function normalizeStage(string $stage): string
    {
        return strtolower(trim($stage));
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function priorityFromQuadrant(string $quadrant): string
    {
        return match ($quadrant) {
            'quick_win' => 'high',
            'big_bet' => 'medium',
            default => 'low',
        };
    }
}
