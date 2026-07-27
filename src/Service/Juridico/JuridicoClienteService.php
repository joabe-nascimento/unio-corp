<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoCliente;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Support\BrPersonFormat;
use Doctrine\ORM\EntityManagerInterface;

class JuridicoClienteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoClienteRepository $repo,
        private JuridicoProcessoRepository $processoRepo,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): JuridicoCliente
    {
        $cliente = new JuridicoCliente();
        $cliente->setEmpresa($empresa);
        $this->applyData($cliente, $data);
        $this->em->persist($cliente);
        $this->em->flush();

        return $cliente;
    }

    /** @param array<string, mixed> $data */
    public function update(JuridicoCliente $cliente, array $data): void
    {
        $this->applyData($cliente, $data);
        $cliente->touch();
        $this->em->flush();
    }

    public function delete(JuridicoCliente $cliente): void
    {
        if ($this->processoRepo->countByCliente($cliente) > 0) {
            throw new JuridicoProcessException('Não é possível excluir: existem processos vinculados a este cliente.');
        }
        $this->em->remove($cliente);
        $this->em->flush();
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoCliente
    {
        $cliente = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$cliente) {
            throw new JuridicoProcessException('Cliente não encontrado.');
        }

        return $cliente;
    }

    /** @return list<JuridicoCliente> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        return $this->repo->findForEmpresa($empresa, $status, $q);
    }

    /** @return list<JuridicoCliente> */
    public function listForSelect(Empresa $empresa): array
    {
        return $this->repo->findAllForSelect($empresa);
    }

    public function processosAtivos(JuridicoCliente $cliente): int
    {
        return $this->processoRepo->countByCliente($cliente);
    }

    public function valorCarteira(JuridicoCliente $cliente): float
    {
        return $this->processoRepo->sumValorByCliente($cliente);
    }

    /** @param array<string, mixed> $data */
    private function applyData(JuridicoCliente $cliente, array $data): void
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw new JuridicoProcessException('Informe o nome do cliente.');
        }

        $tipo = strtoupper(trim((string) ($data['tipo'] ?? JuridicoCliente::TIPO_PJ)));
        if (!\in_array($tipo, JuridicoCliente::TIPOS, true)) {
            $tipo = JuridicoCliente::TIPO_PJ;
        }

        $status = trim((string) ($data['status'] ?? JuridicoCliente::STATUS_STANDARD));
        if (!\in_array($status, JuridicoCliente::STATUSES, true)) {
            $status = JuridicoCliente::STATUS_STANDARD;
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new JuridicoProcessException('Informe um e-mail válido.');
        }

        $cliente->setNome($nome);
        $cliente->setTipo($tipo);
        $cliente->setDocumento(BrPersonFormat::digitsOnly($this->nullIfEmpty($data['documento'] ?? null)));
        $cliente->setEmail($this->nullIfEmpty($email));
        $cliente->setTelefone(BrPersonFormat::digitsOnly($this->nullIfEmpty($data['telefone'] ?? null)));
        $cliente->setAreaAtuacao($this->nullIfEmpty($data['area_atuacao'] ?? null));
        $cliente->setStatus($status);
        $cliente->setObservacoes($this->nullIfEmpty($data['observacoes'] ?? null));
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
