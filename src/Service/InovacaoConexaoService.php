<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\InovConexao;
use App\Entity\User;
use App\Repository\InovConexaoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InovacaoConexaoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InovConexaoRepository $repo,
    ) {}

    /** @return list<InovConexao> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findByEmpresa($empresa);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): InovConexao
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if (!$item) {
            throw new \InvalidArgumentException('Conexão não encontrada.');
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromForm(Empresa $empresa, User $autor, array $data): InovConexao
    {
        $hub = trim((string) ($data['hub'] ?? ''));
        $oportunidade = trim((string) ($data['oportunidade'] ?? ''));
        $acao = trim((string) ($data['acao'] ?? ''));
        if ($hub === '' || $oportunidade === '' || $acao === '') {
            throw new \InvalidArgumentException('Hub, oportunidade e ação são obrigatórios.');
        }

        $conexao = new InovConexao();
        $conexao->setEmpresa($empresa);
        $conexao->setAutor($autor);
        $this->applyFormData($conexao, $data);
        $conexao->setHub($hub);
        $conexao->setOportunidade($oportunidade);
        $conexao->setAcao($acao);

        $this->em->persist($conexao);
        $this->em->flush();

        return $conexao;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFromForm(InovConexao $conexao, array $data): void
    {
        $hub = trim((string) ($data['hub'] ?? $conexao->getHub()));
        $oportunidade = trim((string) ($data['oportunidade'] ?? $conexao->getOportunidade()));
        $acao = trim((string) ($data['acao'] ?? $conexao->getAcao()));
        if ($hub === '' || $oportunidade === '' || $acao === '') {
            throw new \InvalidArgumentException('Hub, oportunidade e ação são obrigatórios.');
        }

        $conexao->setHub($hub);
        $conexao->setOportunidade($oportunidade);
        $conexao->setAcao($acao);
        $this->applyFormData($conexao, $data);
        $conexao->touch();
        $this->em->flush();
    }

    public function delete(InovConexao $conexao): void
    {
        $this->em->remove($conexao);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    public function toArray(InovConexao $conexao): array
    {
        return [
            'id' => $conexao->getId(),
            'hub' => $conexao->getHub(),
            'icon' => $conexao->getIcon(),
            'synergy' => $conexao->getSinergia(),
            'status' => $conexao->getStatus(),
            'opportunity' => $conexao->getOportunidade(),
            'action' => $conexao->getAcao(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFormData(InovConexao $conexao, array $data): void
    {
        if (isset($data['icon'])) {
            $icon = trim((string) $data['icon']);
            if ($icon !== '') {
                $conexao->setIcon($icon);
            }
        }
        if (isset($data['sinergia']) || isset($data['synergy'])) {
            $conexao->setSinergia((int) ($data['sinergia'] ?? $data['synergy']));
        }
        if (isset($data['status'])) {
            $conexao->setStatus((string) $data['status']);
        }
    }
}
