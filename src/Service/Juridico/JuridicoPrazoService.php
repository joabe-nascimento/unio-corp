<?php

namespace App\Service\Juridico;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\JuridicoPrazo;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class JuridicoPrazoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoPrazoRepository $repo,
        private JuridicoProcessoRepository $processoRepo,
        private UserRepository $userRepo,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): JuridicoPrazo
    {
        $prazo = new JuridicoPrazo();
        $prazo->setEmpresa($empresa);
        $this->applyData($empresa, $prazo, $data);
        $this->em->persist($prazo);
        $this->em->flush();

        return $prazo;
    }

    /** @param array<string, mixed> $data */
    public function update(JuridicoPrazo $prazo, array $data): void
    {
        $this->applyData($prazo->getEmpresa(), $prazo, $data);
        $prazo->touch();
        $this->em->flush();
    }

    public function marcarCumprido(JuridicoPrazo $prazo, bool $cumprido = true): void
    {
        $prazo->setCumprido($cumprido);
        $prazo->touch();
        $this->em->flush();
    }

    public function delete(JuridicoPrazo $prazo): void
    {
        $this->em->remove($prazo);
        $this->em->flush();
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoPrazo
    {
        $prazo = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$prazo) {
            throw new JuridicoProcessException('Prazo não encontrado.');
        }

        return $prazo;
    }

    /** @return list<JuridicoPrazo> */
    public function findForEmpresa(Empresa $empresa, ?string $situacao = null, ?string $q = null): array
    {
        return $this->repo->findForEmpresa($empresa, $situacao, $q);
    }

    /** @param array<string, mixed> $data */
    private function applyData(Empresa $empresa, JuridicoPrazo $prazo, array $data): void
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));
        if ($tipo === '') {
            throw new JuridicoProcessException('Informe o tipo do prazo.');
        }

        $dataLimite = DateNormalizer::fromFormDate($data['data_limite'] ?? null);
        if (!$dataLimite) {
            throw new JuridicoProcessException('Informe uma data limite válida.');
        }

        $prazo->setTipo($tipo);
        $prazo->setDescricao($this->nullIfEmpty($data['descricao'] ?? null));
        $prazo->setDataLimite($dataLimite);
        $prazo->setCumprido((bool) ($data['cumprido'] ?? false));

        $processoId = (int) ($data['processo_id'] ?? 0);
        if ($processoId > 0) {
            $processo = $this->processoRepo->findOneByEmpresa($empresa, $processoId);
            $prazo->setProcesso($processo);
        } else {
            $prazo->setProcesso(null);
        }

        $responsavelId = (int) ($data['responsavel_id'] ?? 0);
        if ($responsavelId > 0) {
            $responsavel = $this->userRepo->findOneBy(['id' => $responsavelId, 'empresa' => $empresa]);
            $prazo->setResponsavel($responsavel);
        } else {
            $prazo->setResponsavel(null);
        }
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
