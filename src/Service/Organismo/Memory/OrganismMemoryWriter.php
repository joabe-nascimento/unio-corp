<?php

namespace App\Service\Organismo\Memory;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoMemoryFact;
use App\Entity\PosOperatorioPaciente;
use App\Repository\Organismo\OrganismoMemoryFactRepository;
use Doctrine\ORM\EntityManagerInterface;

final class OrganismMemoryWriter
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrganismoMemoryFactRepository $facts,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function remember(
        Empresa $empresa,
        string $tipo,
        string $sujeito,
        array $payload = [],
        int $peso = 5,
        ?PosOperatorioPaciente $paciente = null,
    ): OrganismoMemoryFact {
        $fact = new OrganismoMemoryFact();
        $fact->setEmpresa($empresa)
            ->setTipo($tipo)
            ->setSujeito(mb_substr($sujeito, 0, 160))
            ->setPayload($payload)
            ->setPeso($peso)
            ->setPaciente($paciente);
        $this->em->persist($fact);

        return $fact;
    }
}
