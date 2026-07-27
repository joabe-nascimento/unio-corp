<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\UserRepository;
use App\Support\BrPersonFormat;
use Doctrine\ORM\EntityManagerInterface;

class JuridicoProcessoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoProcessoRepository $repo,
        private JuridicoClienteRepository $clienteRepo,
        private UserRepository $userRepo,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): JuridicoProcesso
    {
        $processo = new JuridicoProcesso();
        $processo->setEmpresa($empresa);
        $this->applyData($empresa, $processo, $data);
        $this->em->persist($processo);
        $this->em->flush();

        return $processo;
    }

    /** @param array<string, mixed> $data */
    public function update(JuridicoProcesso $processo, array $data): void
    {
        $this->applyData($processo->getEmpresa(), $processo, $data);
        $processo->touch();
        $this->em->flush();
    }

    public function delete(JuridicoProcesso $processo): void
    {
        $this->em->remove($processo);
        $this->em->flush();
    }

    /** Usado pelo Kanban de fases (drag-and-drop). */
    public function atualizarFase(JuridicoProcesso $processo, string $fase): void
    {
        if (!\in_array($fase, JuridicoProcesso::FASES, true)) {
            throw new JuridicoProcessException('Fase inválida.');
        }

        $processo->setFase($fase);
        if ($fase === JuridicoProcesso::FASE_ENCERRADO && $processo->getStatus() !== JuridicoProcesso::STATUS_ENCERRADO) {
            $processo->setStatus(JuridicoProcesso::STATUS_ENCERRADO);
        }
        $processo->touch();
        $this->em->flush();
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoProcesso
    {
        $processo = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$processo) {
            throw new JuridicoProcessException('Processo não encontrado.');
        }

        return $processo;
    }

    /** @return list<JuridicoProcesso> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        return $this->repo->findForEmpresa($empresa, $status, $q);
    }

    /** @return list<JuridicoProcesso> */
    public function listForSelect(Empresa $empresa): array
    {
        return $this->repo->findAllForSelect($empresa);
    }

    /**
     * Painel de indicadores da carteira de processos do escritório.
     *
     * @return array{total: int, ativos: int, criticos: int, encerrados: int, valorAtivo: float, taxaExito: ?float}
     */
    public function estatisticas(Empresa $empresa): array
    {
        return [
            'total' => $this->repo->countByEmpresa($empresa),
            'ativos' => $this->repo->countByEmpresaAndStatus($empresa, JuridicoProcesso::STATUS_ATIVO),
            'criticos' => $this->repo->countByEmpresaAndStatus($empresa, JuridicoProcesso::STATUS_CRITICO),
            'encerrados' => $this->repo->countByEmpresaAndStatus($empresa, JuridicoProcesso::STATUS_ENCERRADO),
            'valorAtivo' => $this->repo->sumValorAtivoByEmpresa($empresa),
            'taxaExito' => $this->repo->taxaExito($empresa),
        ];
    }

    /** @param array<string, mixed> $data */
    private function applyData(Empresa $empresa, JuridicoProcesso $processo, array $data): void
    {
        $numero = trim((string) ($data['numero'] ?? ''));
        if ($numero === '') {
            throw new JuridicoProcessException('Informe o número do processo.');
        }

        $fase = trim((string) ($data['fase'] ?? JuridicoProcesso::FASE_CONHECIMENTO));
        if (!\in_array($fase, JuridicoProcesso::FASES, true)) {
            $fase = JuridicoProcesso::FASE_CONHECIMENTO;
        }

        $status = trim((string) ($data['status'] ?? JuridicoProcesso::STATUS_ATIVO));
        if (!\in_array($status, JuridicoProcesso::STATUSES, true)) {
            $status = JuridicoProcesso::STATUS_ATIVO;
        }

        $resultado = trim((string) ($data['resultado'] ?? ''));
        if ($resultado !== '' && !\in_array($resultado, JuridicoProcesso::RESULTADOS, true)) {
            $resultado = '';
        }

        $processo->setNumero($numero);
        $processo->setArea($this->nullIfEmpty($data['area'] ?? null));
        $processo->setFase($fase);
        $processo->setTribunal($this->nullIfEmpty($data['tribunal'] ?? null));
        $processo->setStatus($status);
        $processo->setResultado($resultado !== '' ? $resultado : null);
        $processo->setValor(BrPersonFormat::parseMoney($data['valor'] ?? null));
        $processo->setObservacoes($this->nullIfEmpty($data['observacoes'] ?? null));

        $clienteId = (int) ($data['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            $cliente = $this->clienteRepo->findOneByEmpresa($empresa, $clienteId);
            $processo->setCliente($cliente);
        } else {
            $processo->setCliente(null);
        }

        $responsavelId = (int) ($data['responsavel_id'] ?? 0);
        if ($responsavelId > 0) {
            $responsavel = $this->userRepo->findOneBy(['id' => $responsavelId, 'empresa' => $empresa]);
            $processo->setResponsavel($responsavel);
        } else {
            $processo->setResponsavel(null);
        }
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
