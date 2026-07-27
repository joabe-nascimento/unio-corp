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
    ) {}

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
