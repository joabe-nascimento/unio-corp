<?php

namespace App\Service\Juridico;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\JuridicoJurisprudencia;
use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoJurisprudenciaRepository;
use Doctrine\ORM\EntityManagerInterface;

class JuridicoJurisprudenciaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoJurisprudenciaRepository $repo,
        private JurisFlowAiClient $jurisFlowAi,
    ) {}

    /**
     * Pesquisa jurisprudencial com IA (Bruna + JurisFlow): retorna teses e
     * julgados sugeridos, prontos para virar registros na biblioteca do
     * escritório com um clique.
     *
     * @return array{resultados: list<array{tribunal: string, tema: string, resultado: ?string, relevancia: string, referencia: ?string, resumo: ?string}>, disclaimer: string}
     */
    public function buscarComIA(Empresa $empresa, string $tema, string $tribunal = 'Todos', string $periodo = '', string $areaJuridica = 'Geral'): array
    {
        $tema = trim($tema);
        if ($tema === '') {
            throw new JuridicoProcessException('Informe um tema para pesquisar.');
        }

        $resultado = $this->jurisFlowAi->pesquisarJurisprudencia(
            $tema,
            $tribunal ?: 'Todos',
            $periodo,
            $areaJuridica ?: 'Geral',
            (string) $empresa->getId(),
        );

        if ($resultado === null) {
            throw new JuridicoProcessException('A pesquisa com IA está temporariamente indisponível. Tente novamente em instantes.');
        }

        if ($resultado['resultados'] === []) {
            throw new JuridicoProcessException('Nenhum resultado encontrado para esse tema. Tente reformular a pesquisa.');
        }

        return $resultado;
    }

    /** Salva uma sugestão da pesquisa com IA como registro na biblioteca do escritório. */
    public function salvarSugestao(Empresa $empresa, array $sugestao, ?User $createdBy): JuridicoJurisprudencia
    {
        return $this->create($empresa, [
            'tribunal' => $sugestao['tribunal'] ?? '',
            'tema' => $sugestao['tema'] ?? '',
            'resultado' => $sugestao['resultado'] ?? null,
            'relevancia' => $sugestao['relevancia'] ?? JuridicoJurisprudencia::RELEVANCIA_MEDIA,
            'referencia' => $sugestao['referencia'] ?? null,
            'resumo' => $sugestao['resumo'] ?? null,
        ], $createdBy);
    }

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data, ?User $createdBy): JuridicoJurisprudencia
    {
        $item = new JuridicoJurisprudencia();
        $item->setEmpresa($empresa);
        $item->setCreatedBy($createdBy);
        $this->applyData($item, $data);
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    /** @param array<string, mixed> $data */
    public function update(JuridicoJurisprudencia $item, array $data): void
    {
        $this->applyData($item, $data);
        $item->touch();
        $this->em->flush();
    }

    public function delete(JuridicoJurisprudencia $item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoJurisprudencia
    {
        $item = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$item) {
            throw new JuridicoProcessException('Registro não encontrado.');
        }

        return $item;
    }

    /** @return list<JuridicoJurisprudencia> */
    public function findForEmpresa(Empresa $empresa, ?string $relevancia = null, ?string $q = null): array
    {
        return $this->repo->findForEmpresa($empresa, $relevancia, $q);
    }

    /** @param array<string, mixed> $data */
    private function applyData(JuridicoJurisprudencia $item, array $data): void
    {
        $tribunal = trim((string) ($data['tribunal'] ?? ''));
        $tema = trim((string) ($data['tema'] ?? ''));
        if ($tribunal === '' || $tema === '') {
            throw new JuridicoProcessException('Informe o tribunal e o tema.');
        }

        $relevancia = trim((string) ($data['relevancia'] ?? JuridicoJurisprudencia::RELEVANCIA_MEDIA));
        if (!\in_array($relevancia, JuridicoJurisprudencia::RELEVANCIAS, true)) {
            $relevancia = JuridicoJurisprudencia::RELEVANCIA_MEDIA;
        }

        $item->setTribunal($tribunal);
        $item->setTema($tema);
        $item->setData(DateNormalizer::fromFormDate($data['data'] ?? null));
        $item->setResultado($this->nullIfEmpty($data['resultado'] ?? null));
        $item->setRelevancia($relevancia);
        $item->setReferencia($this->nullIfEmpty($data['referencia'] ?? null));
        $item->setResumo($this->nullIfEmpty($data['resumo'] ?? null));
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
