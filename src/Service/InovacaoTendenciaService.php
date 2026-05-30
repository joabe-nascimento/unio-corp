<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\InovTendencia;
use App\Repository\InovTendenciaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InovacaoTendenciaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InovTendenciaRepository $repo,
    ) {}

    /** @return list<InovTendencia> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findByEmpresa($empresa);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): InovTendencia
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if (!$item) {
            throw new \InvalidArgumentException('Tendência não encontrada.');
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromForm(Empresa $empresa, array $data): InovTendencia
    {
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException('Label é obrigatório.');
        }

        $tendencia = new InovTendencia();
        $tendencia->setEmpresa($empresa);
        $tendencia->setLabel($label);
        $this->applyFormData($tendencia, $data);

        if (!isset($data['ordem'])) {
            $tendencia->setOrdem(\count($this->repo->findByEmpresa($empresa)));
        }

        $this->em->persist($tendencia);
        $this->em->flush();

        return $tendencia;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFromForm(InovTendencia $tendencia, array $data): void
    {
        $label = trim((string) ($data['label'] ?? $tendencia->getLabel()));
        if ($label === '') {
            throw new \InvalidArgumentException('Label é obrigatório.');
        }

        $tendencia->setLabel($label);
        $this->applyFormData($tendencia, $data);
        $tendencia->touch();
        $this->em->flush();
    }

    public function delete(InovTendencia $tendencia): void
    {
        $this->em->remove($tendencia);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    public function toArray(InovTendencia $tendencia): array
    {
        return [
            'id' => $tendencia->getId(),
            'label' => $tendencia->getLabel(),
            'value' => $tendencia->getValor(),
            'hint' => $tendencia->getHint() ?? '',
            'status' => $tendencia->getStatus(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFormData(InovTendencia $tendencia, array $data): void
    {
        if (isset($data['valor']) || isset($data['value'])) {
            $tendencia->setValor((int) ($data['valor'] ?? $data['value']));
        }
        if (isset($data['hint'])) {
            $tendencia->setHint($this->nullIfEmpty($data['hint']));
        }
        if (isset($data['status'])) {
            $tendencia->setStatus((string) $data['status']);
        }
        if (isset($data['ordem'])) {
            $tendencia->setOrdem((int) $data['ordem']);
        }
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
