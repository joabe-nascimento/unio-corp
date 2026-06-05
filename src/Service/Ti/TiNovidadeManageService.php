<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiNovidade;
use App\Entity\User;
use App\Repository\TiNovidadeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TiNovidadeManageService
{
    private const VARIANTS = ['info', 'success', 'warning', 'danger', 'secondary'];

    public function __construct(
        private EntityManagerInterface $em,
        private TiNovidadeRepository $repo,
    ) {}

    public function loadForEmpresa(Empresa $empresa, int $id): TiNovidade
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if (!$item) {
            throw new \InvalidArgumentException('Comunicado não encontrado.');
        }

        return $item;
    }

    /** @param array<string, mixed> $data */
    public function createFromForm(Empresa $empresa, User $autor, array $data): TiNovidade
    {
        $novidade = (new TiNovidade())
            ->setEmpresa($empresa)
            ->setAutor($autor);
        $this->applyFormData($novidade, $data);

        $this->em->persist($novidade);
        $this->em->flush();

        return $novidade;
    }

    /** @param array<string, mixed> $data */
    public function updateFromForm(TiNovidade $novidade, array $data): void
    {
        $this->applyFormData($novidade, $data);
        $this->em->flush();
    }

    public function delete(TiNovidade $novidade): void
    {
        $this->em->remove($novidade);
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    private function applyFormData(TiNovidade $novidade, array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? $data['title'] ?? ''));
        $resumo = trim((string) ($data['resumo'] ?? $data['summary'] ?? ''));
        if ($titulo === '' || $resumo === '') {
            throw new \InvalidArgumentException('Título e resumo são obrigatórios.');
        }

        $novidade->setTitulo($titulo);
        $novidade->setResumo($resumo);

        $icon = trim((string) ($data['icon'] ?? 'fa-bullhorn'));
        $novidade->setIcon($icon !== '' ? $icon : 'fa-bullhorn');

        $badge = trim((string) ($data['badge'] ?? ''));
        $novidade->setBadge($badge !== '' ? $badge : null);

        $variant = (string) ($data['variant'] ?? $novidade->getVariant());
        if (!\in_array($variant, self::VARIANTS, true)) {
            throw new \InvalidArgumentException('Variante inválida.');
        }
        $novidade->setVariant($variant);

        $publicado = trim((string) ($data['publicado_em'] ?? ''));
        if ($publicado !== '') {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $publicado)
                ?: \DateTimeImmutable::createFromFormat('d/m/Y', $publicado);
            if (!$date) {
                throw new \InvalidArgumentException('Data de publicação inválida.');
            }
            $novidade->setPublicadoEm($date);
        }
    }
}
