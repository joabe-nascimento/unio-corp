<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhFolhaCompetencia;
use App\Entity\RhFolhaLancamento;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhFolhaCompetenciaRepository;
use App\Repository\RhFolhaLancamentoRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhFolhaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhFolhaCompetenciaRepository $competenciaRepo,
        private RhFolhaLancamentoRepository $lancamentoRepo,
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    public function gerarCompetencia(Empresa $empresa, string $referencia): RhFolhaCompetencia
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $referencia)) {
            throw new RhProcessException('Referência inválida. Use o formato AAAA-MM.');
        }

        $existing = $this->competenciaRepo->findOneByReferencia($empresa, $referencia);
        if ($existing) {
            throw new RhProcessException('Já existe folha para esta competência.');
        }

        $comp = new RhFolhaCompetencia();
        $comp->setEmpresa($empresa);
        $comp->setReferencia($referencia);
        $comp->setStatus(RhFolhaCompetencia::STATUS_ABERTA);

        $funcionarios = $this->funcionarioRepo->findBy(['empresa' => $empresa, 'status' => 'ATIVO']);
        foreach ($funcionarios as $func) {
            $salario = (float) ($func->getSalario() ?? 0);
            if ($salario <= 0) {
                continue;
            }
            $lanc = new RhFolhaLancamento();
            $lanc->setCompetencia($comp);
            $lanc->setFuncionario($func);
            $lanc->setTipo(RhFolhaLancamento::TIPO_PROVENTO);
            $lanc->setCodigo('SALARIO');
            $lanc->setDescricao('Salário base');
            $lanc->setValor(number_format($salario, 2, '.', ''));
            $comp->getLancamentos()->add($lanc);
            $this->em->persist($lanc);
        }

        $this->recalcularTotais($comp);
        $this->em->persist($comp);
        $this->em->flush();

        return $comp;
    }

    public function adicionarLancamento(
        RhFolhaCompetencia $comp,
        Funcionario $funcionario,
        string $tipo,
        string $codigo,
        string $descricao,
        string $valor,
    ): RhFolhaLancamento {
        if ($comp->isFechada()) {
            throw new RhProcessException('Competência fechada. Não é possível adicionar lançamentos.');
        }
        if ($funcionario->getEmpresa()?->getId() !== $comp->getEmpresa()->getId()) {
            throw new RhProcessException('Funcionário inválido para esta competência.');
        }
        if (!\in_array($tipo, [RhFolhaLancamento::TIPO_PROVENTO, RhFolhaLancamento::TIPO_DESCONTO], true)) {
            throw new RhProcessException('Tipo de lançamento inválido.');
        }
        $v = (float) str_replace(',', '.', $valor);
        if ($v <= 0) {
            throw new RhProcessException('Valor deve ser maior que zero.');
        }

        $lanc = new RhFolhaLancamento();
        $lanc->setCompetencia($comp);
        $lanc->setFuncionario($funcionario);
        $lanc->setTipo($tipo);
        $lanc->setCodigo(strtoupper(substr(trim($codigo), 0, 32)));
        $lanc->setDescricao(trim($descricao));
        $lanc->setValor(number_format($v, 2, '.', ''));

        $this->em->persist($lanc);
        $comp->getLancamentos()->add($lanc);
        $this->recalcularTotais($comp);
        $this->em->flush();

        return $lanc;
    }

    public function fecharCompetencia(RhFolhaCompetencia $comp): void
    {
        if ($comp->isFechada()) {
            throw new RhProcessException('Esta competência já está fechada.');
        }
        if ($comp->getLancamentos()->isEmpty()) {
            throw new RhProcessException('Não há lançamentos para fechar a folha.');
        }

        $comp->setStatus(RhFolhaCompetencia::STATUS_FECHADA);
        $comp->setFechadoEm(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function recalcularTotais(RhFolhaCompetencia $comp): void
    {
        $proventos = 0.0;
        $descontos = 0.0;
        foreach ($comp->getLancamentos() as $l) {
            if ($l->getTipo() === RhFolhaLancamento::TIPO_PROVENTO) {
                $proventos += $l->getValorFloat();
            } else {
                $descontos += $l->getValorFloat();
            }
        }
        $comp->setTotalProventos(number_format($proventos, 2, '.', ''));
        $comp->setTotalDescontos(number_format($descontos, 2, '.', ''));
        $comp->setTotalLiquido(number_format($proventos - $descontos, 2, '.', ''));
    }

    /** @return list<RhFolhaCompetencia> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->competenciaRepo->findByEmpresa($empresa);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): RhFolhaCompetencia
    {
        $c = $this->competenciaRepo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if (!$c) {
            throw new RhProcessException('Competência não encontrada.');
        }

        return $c;
    }

    /** @return list<RhFolhaLancamento> */
    public function lancamentosPorCompetencia(RhFolhaCompetencia $comp): array
    {
        return $this->lancamentoRepo->findByCompetencia($comp);
    }

    /**
     * @return list<array{funcionario: Funcionario, proventos: float, descontos: float, liquido: float}>
     */
    public function resumoPorFuncionario(RhFolhaCompetencia $comp): array
    {
        $map = [];
        foreach ($this->lancamentoRepo->findByCompetencia($comp) as $l) {
            $fid = $l->getFuncionario()->getId();
            if (!isset($map[$fid])) {
                $map[$fid] = ['funcionario' => $l->getFuncionario(), 'proventos' => 0.0, 'descontos' => 0.0, 'liquido' => 0.0];
            }
            if ($l->getTipo() === RhFolhaLancamento::TIPO_PROVENTO) {
                $map[$fid]['proventos'] += $l->getValorFloat();
            } else {
                $map[$fid]['descontos'] += $l->getValorFloat();
            }
            $map[$fid]['liquido'] = $map[$fid]['proventos'] - $map[$fid]['descontos'];
        }

        return array_values($map);
    }

    public function exportCsv(RhFolhaCompetencia $comp): string
    {
        $lines = ["funcionario_id;nome;tipo;codigo;descricao;valor"];
        foreach ($this->lancamentoRepo->findByCompetencia($comp) as $l) {
            $f = $l->getFuncionario();
            $lines[] = sprintf(
                '%d;%s;%s;%s;%s;%s',
                $f->getId(),
                str_replace(';', ',', $f->getNome() ?? ''),
                $l->getTipo(),
                $l->getCodigo(),
                str_replace(';', ',', $l->getDescricao()),
                $l->getValor()
            );
        }

        return implode("\n", $lines);
    }

    public function competenciaAtualLabel(): string
    {
        return (new \DateTimeImmutable())->format('Y-m');
    }
}
