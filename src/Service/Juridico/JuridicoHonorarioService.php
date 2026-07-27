<?php

namespace App\Service\Juridico;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\JuridicoHonorarioLancamento;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoHonorarioLancamentoRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\UserRepository;
use App\Support\BrPersonFormat;
use Doctrine\ORM\EntityManagerInterface;

class JuridicoHonorarioService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoHonorarioLancamentoRepository $repo,
        private JuridicoProcessoRepository $processoRepo,
        private UserRepository $userRepo,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): JuridicoHonorarioLancamento
    {
        $lancamento = new JuridicoHonorarioLancamento();
        $lancamento->setEmpresa($empresa);
        $this->applyData($empresa, $lancamento, $data);
        $this->em->persist($lancamento);
        $this->em->flush();

        return $lancamento;
    }

    /** @param array<string, mixed> $data */
    public function update(JuridicoHonorarioLancamento $lancamento, array $data): void
    {
        $this->applyData($lancamento->getEmpresa(), $lancamento, $data);
        $lancamento->touch();
        $this->em->flush();
    }

    public function delete(JuridicoHonorarioLancamento $lancamento): void
    {
        $this->em->remove($lancamento);
        $this->em->flush();
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoHonorarioLancamento
    {
        $lancamento = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$lancamento) {
            throw new JuridicoProcessException('Lançamento não encontrado.');
        }

        return $lancamento;
    }

    /** @return list<JuridicoHonorarioLancamento> */
    public function findForEmpresa(Empresa $empresa, ?int $advogadoId = null, ?string $mes = null): array
    {
        return $this->repo->findForEmpresa($empresa, $advogadoId, $mes);
    }

    /** @return array<int, array{advogado: \App\Entity\User, horas: float, receita: float}> */
    public function resumoPorAdvogado(Empresa $empresa, ?string $mes = null): array
    {
        return $this->repo->resumoPorAdvogado($empresa, $mes);
    }

    public function receitaMes(Empresa $empresa, ?string $mes = null): float
    {
        return $this->repo->sumReceitaMes($empresa, $mes);
    }

    public function horasMes(Empresa $empresa, ?string $mes = null): float
    {
        return $this->repo->sumHorasMes($empresa, $mes);
    }

    /** @param array<string, mixed> $data */
    private function applyData(Empresa $empresa, JuridicoHonorarioLancamento $lancamento, array $data): void
    {
        $advogadoId = (int) ($data['advogado_id'] ?? 0);
        if ($advogadoId <= 0) {
            throw new JuridicoProcessException('Selecione o advogado responsável pelo lançamento.');
        }
        $advogado = $this->userRepo->findOneBy(['id' => $advogadoId, 'empresa' => $empresa]);
        if (!$advogado) {
            throw new JuridicoProcessException('Advogado inválido.');
        }

        $data_ = DateNormalizer::fromFormDate($data['data'] ?? null);
        if (!$data_) {
            throw new JuridicoProcessException('Informe a data do lançamento.');
        }

        $horas = (float) str_replace(',', '.', (string) ($data['horas'] ?? '0'));
        if ($horas <= 0) {
            throw new JuridicoProcessException('Informe a quantidade de horas trabalhadas.');
        }

        $lancamento->setAdvogado($advogado);
        $lancamento->setData($data_);
        $lancamento->setHoras(number_format($horas, 2, '.', ''));
        $lancamento->setValorHora(BrPersonFormat::parseMoney($data['valor_hora'] ?? null));
        $lancamento->setDescricao($this->nullIfEmpty($data['descricao'] ?? null));
        $lancamento->setFaturavel((bool) ($data['faturavel'] ?? true));

        $processoId = (int) ($data['processo_id'] ?? 0);
        if ($processoId > 0) {
            $processo = $this->processoRepo->findOneByEmpresa($empresa, $processoId);
            $lancamento->setProcesso($processo);
        } else {
            $lancamento->setProcesso(null);
        }
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
