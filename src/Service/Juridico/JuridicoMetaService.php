<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoMeta;
use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoHonorarioLancamentoRepository;
use App\Repository\JuridicoMetaRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Metas de desempenho por escritório, área ou advogado — fecha o item de
 * roadmap "Metas por sócio/área" do Analytics Jurídico. Compara o valor
 * configurado com o dado real já calculado pelos serviços de carteira e
 * honorários (sem duplicar lógica de agregação).
 */
class JuridicoMetaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoMetaRepository $repo,
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoHonorarioLancamentoRepository $honorarioRepo,
        private UserRepository $userRepo,
    ) {
    }

    /**
     * @return list<array{meta: JuridicoMeta, atual: float, percentual: int, atingida: bool}>
     */
    public function progresso(Empresa $empresa, ?string $periodo = null): array
    {
        $periodo ??= (new \DateTimeImmutable('today'))->format('Y-m');
        $metas = $this->repo->findForEmpresaPeriodo($empresa, $periodo);

        $out = [];
        foreach ($metas as $meta) {
            $atual = $this->valorAtual($empresa, $meta);
            $alvo = (float) $meta->getValorMeta();
            $percentual = $alvo > 0 ? (int) min(999, round(($atual / $alvo) * 100)) : 0;

            $out[] = [
                'meta' => $meta,
                'atual' => $atual,
                'percentual' => $percentual,
                'atingida' => $atual >= $alvo,
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data, ?User $criadoPor): JuridicoMeta
    {
        $tipo = (string) ($data['tipo'] ?? '');
        if (!\in_array($tipo, JuridicoMeta::TIPOS, true)) {
            throw new JuridicoProcessException('Selecione um tipo de meta válido.');
        }

        $periodo = (string) ($data['periodo'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new JuridicoProcessException('Informe o período da meta (mês/ano).');
        }

        $valor = (float) str_replace(',', '.', (string) ($data['valor_meta'] ?? '0'));
        if ($valor <= 0) {
            throw new JuridicoProcessException('Informe um valor de meta maior que zero.');
        }

        $escopo = (string) ($data['escopo'] ?? 'escritorio');
        $area = null;
        $responsavel = null;

        if ($escopo === 'area') {
            $area = trim((string) ($data['area'] ?? ''));
            if ($area === '') {
                throw new JuridicoProcessException('Informe a área para a meta.');
            }
        } elseif ($escopo === 'responsavel') {
            $responsavelId = (int) ($data['responsavel_id'] ?? 0);
            $responsavel = $responsavelId > 0 ? $this->userRepo->find($responsavelId) : null;
            if (!$responsavel) {
                throw new JuridicoProcessException('Selecione um advogado responsável para a meta.');
            }
        }

        $meta = new JuridicoMeta();
        $meta->setEmpresa($empresa);
        $meta->setTipo($tipo);
        $meta->setPeriodo($periodo);
        $meta->setValorMeta(number_format($valor, 2, '.', ''));
        $meta->setArea($area);
        $meta->setResponsavel($responsavel);
        $meta->setCriadoPor($criadoPor);

        $this->em->persist($meta);
        $this->em->flush();

        return $meta;
    }

    public function delete(JuridicoMeta $meta): void
    {
        $this->em->remove($meta);
        $this->em->flush();
    }

    private function valorAtual(Empresa $empresa, JuridicoMeta $meta): float
    {
        $periodo = $meta->getPeriodo();

        if ($meta->getTipo() === JuridicoMeta::TIPO_RECEITA) {
            if ($meta->getResponsavel() !== null) {
                foreach ($this->honorarioRepo->resumoPorAdvogado($empresa, $periodo) as $linha) {
                    if ($linha['advogado']->getId() === $meta->getResponsavel()->getId()) {
                        return $linha['receita'];
                    }
                }

                return 0.0;
            }

            if ($meta->getArea() !== null) {
                return $this->honorarioRepo->sumReceitaMesPorArea($empresa, $meta->getArea(), $periodo);
            }

            return $this->honorarioRepo->sumReceitaMes($empresa, $periodo);
        }

        if ($meta->getResponsavel() !== null) {
            foreach ($this->processoRepo->resultadosGroupedByResponsavel($empresa) as $responsavelId => $dados) {
                if ((int) $responsavelId === $meta->getResponsavel()->getId()) {
                    return $dados['total'] > 0 ? round(($dados['favoraveis'] / $dados['total']) * 100, 1) : 0.0;
                }
            }

            return 0.0;
        }

        if ($meta->getArea() !== null) {
            $historico = $this->processoRepo->taxaExitoPorArea($empresa);

            return $historico[$meta->getArea()]['taxa'] ?? 0.0;
        }

        return $this->processoRepo->taxaExito($empresa) ?? 0.0;
    }
}
