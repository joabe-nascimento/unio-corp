<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\InovNovidade;
use App\Entity\User;
use App\Repository\InovNovidadeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InovacaoNovidadeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InovNovidadeRepository $repo,
    ) {}

    /** @return list<InovNovidade> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findByEmpresa($empresa);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): InovNovidade
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if (!$item) {
            throw new \InvalidArgumentException('Novidade não encontrada.');
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromForm(Empresa $empresa, User $autor, array $data): InovNovidade
    {
        $titulo = trim((string) ($data['titulo'] ?? $data['title'] ?? ''));
        $resumo = trim((string) ($data['resumo'] ?? $data['summary'] ?? ''));
        if ($titulo === '' || $resumo === '') {
            throw new \InvalidArgumentException('Título e resumo são obrigatórios.');
        }

        $novidade = new InovNovidade();
        $novidade->setEmpresa($empresa);
        $novidade->setAutor($autor);
        $novidade->setTitulo($titulo);
        $novidade->setResumo($resumo);
        $this->applyFormData($novidade, $data);

        $this->em->persist($novidade);
        $this->em->flush();

        return $novidade;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFromForm(InovNovidade $novidade, array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? $data['title'] ?? $novidade->getTitulo()));
        $resumo = trim((string) ($data['resumo'] ?? $data['summary'] ?? $novidade->getResumo()));
        if ($titulo === '' || $resumo === '') {
            throw new \InvalidArgumentException('Título e resumo são obrigatórios.');
        }

        $novidade->setTitulo($titulo);
        $novidade->setResumo($resumo);
        $this->applyFormData($novidade, $data);
        $this->em->flush();
    }

    public function delete(InovNovidade $novidade): void
    {
        $this->em->remove($novidade);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    public function toArray(InovNovidade $novidade): array
    {
        return [
            'id' => $novidade->getId(),
            'date' => $novidade->getPublicadoEm()->format('d/m'),
            'title' => $novidade->getTitulo(),
            'summary' => $novidade->getResumo(),
            'icon' => $novidade->getIcon(),
            'route' => $novidade->getRouteName(),
            'badge' => $novidade->getBadge(),
            'variant' => $novidade->getVariant(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFormData(InovNovidade $novidade, array $data): void
    {
        if (isset($data['icon'])) {
            $icon = trim((string) $data['icon']);
            if ($icon !== '') {
                $novidade->setIcon($icon);
            }
        }
        if (isset($data['route_name']) || isset($data['route'])) {
            $novidade->setRouteName($this->nullIfEmpty($data['route_name'] ?? $data['route'] ?? null));
        }
        if (isset($data['badge'])) {
            $novidade->setBadge($this->nullIfEmpty($data['badge']));
        }
        if (isset($data['variant'])) {
            $novidade->setVariant((string) $data['variant']);
        }
        if (isset($data['publicado_em'])) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $data['publicado_em']);
            if ($parsed) {
                $novidade->setPublicadoEm($parsed);
            }
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
