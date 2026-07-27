<?php

namespace App\Service\Juridico;

use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoProcessoParte;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoProcessoParteRepository;
use Doctrine\ORM\EntityManagerInterface;

class JuridicoProcessoParteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoProcessoParteRepository $repo,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(JuridicoProcesso $processo, array $data): JuridicoProcessoParte
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw new JuridicoProcessException('Informe o nome da parte.');
        }

        $tipo = trim((string) ($data['tipo'] ?? JuridicoProcessoParte::TIPO_OUTRO));
        if (!\in_array($tipo, JuridicoProcessoParte::TIPOS, true)) {
            $tipo = JuridicoProcessoParte::TIPO_OUTRO;
        }

        $polo = trim((string) ($data['polo'] ?? JuridicoProcessoParte::POLO_OUTRO));
        if (!\in_array($polo, JuridicoProcessoParte::POLOS, true)) {
            $polo = JuridicoProcessoParte::POLO_OUTRO;
        }

        $parte = new JuridicoProcessoParte();
        $parte->setProcesso($processo);
        $parte->setNome($nome);
        $parte->setTipo($tipo);
        $parte->setPolo($polo);
        $parte->setDocumento($this->nullIfEmpty($data['documento'] ?? null));
        $parte->setAdvogado($this->nullIfEmpty($data['advogado'] ?? null));
        $parte->setOab($this->nullIfEmpty($data['oab'] ?? null));
        $parte->setPrincipal((bool) ($data['principal'] ?? false));

        $this->em->persist($parte);
        $this->em->flush();

        return $parte;
    }

    public function loadForProcesso(JuridicoProcesso $processo, int $id): JuridicoProcessoParte
    {
        $parte = $this->repo->findOneByProcesso($processo, $id);
        if (!$parte) {
            throw new JuridicoProcessException('Parte não encontrada.');
        }

        return $parte;
    }

    public function delete(JuridicoProcessoParte $parte): void
    {
        $this->em->remove($parte);
        $this->em->flush();
    }

    /** @return list<JuridicoProcessoParte> */
    public function findForProcesso(JuridicoProcesso $processo): array
    {
        return $this->repo->findForProcesso($processo);
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
