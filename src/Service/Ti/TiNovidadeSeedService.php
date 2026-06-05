<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiNovidade;
use App\Repository\TiNovidadeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TiNovidadeSeedService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiNovidadeRepository $repo,
    ) {}

    public function seedIfEmpty(Empresa $empresa): bool
    {
        if ($this->repo->countByEmpresa($empresa) > 0) {
            return false;
        }

        foreach (TiReferenceData::novidadesSeed() as $row) {
            $date = \DateTimeImmutable::createFromFormat('d/m/Y', $row['date'])
                ?: new \DateTimeImmutable();

            $novidade = (new TiNovidade())
                ->setEmpresa($empresa)
                ->setTitulo($row['title'])
                ->setResumo($row['summary'])
                ->setIcon($row['icon'])
                ->setBadge($row['badge'])
                ->setVariant($row['variant'])
                ->setPublicadoEm($date);

            $this->em->persist($novidade);
        }

        $this->em->flush();

        return true;
    }
}
