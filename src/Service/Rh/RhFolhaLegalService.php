<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhFolhaCompetencia;
use App\Entity\RhFolhaHolerite;
use App\Entity\RhFolhaRubrica;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhFolhaHoleriteRepository;
use App\Repository\RhFolhaRubricaRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhFolhaLegalService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhFolhaRubricaRepository $rubricaRepo,
        private RhFolhaHoleriteRepository $holeriteRepo,
        private FuncionarioRepository $funcionarioRepo,
        private RhAuditService $audit,
    ) {}

    public function seedDefaultRubricas(Empresa $empresa): int
    {
        $defaults = [
            ['SALARIO', 'Salário base', RhFolhaRubrica::TIPO_PROVENTO, true, true, true],
            ['INSS', 'INSS', RhFolhaRubrica::TIPO_DESCONTO, false, false, false],
            ['IRRF', 'IRRF', RhFolhaRubrica::TIPO_DESCONTO, false, false, false],
            ['FGTS', 'FGTS (informativo)', RhFolhaRubrica::TIPO_PROVENTO, false, false, false],
        ];

        $created = 0;
        foreach ($defaults as [$codigo, $descricao, $tipo, $inss, $irrf, $fgts]) {
            if ($this->rubricaRepo->findOneBy(['empresa' => $empresa, 'codigo' => $codigo])) {
                continue;
            }
            $rub = new RhFolhaRubrica();
            $rub->setEmpresa($empresa);
            $rub->setCodigo($codigo);
            $rub->setDescricao($descricao);
            $rub->setTipo($tipo);
            $rub->setIncideInss($inss);
            $rub->setIncideIrrf($irrf);
            $rub->setIncideFgts($fgts);
            $this->em->persist($rub);
            ++$created;
        }

        if ($created > 0) {
            $this->em->flush();
        }

        return $created;
    }

    /** @return list<RhFolhaRubrica> */
    public function listRubricas(Empresa $empresa): array
    {
        return $this->rubricaRepo->findForEmpresa($empresa);
    }

    /**
     * Gera holerites legais (stub INSS/IRRF/FGTS) para ativos com salário.
     *
     * @return list<RhFolhaHolerite>
     */
    public function generateHolerites(RhFolhaCompetencia $competencia, ?User $actor = null): array
    {
        $empresa = $competencia->getEmpresa();
        $funcionarios = $this->funcionarioRepo->findBy(['empresa' => $empresa, 'status' => 'ATIVO']);
        $generated = [];

        foreach ($funcionarios as $func) {
            $bruto = (float) ($func->getSalario() ?? 0);
            if ($bruto <= 0) {
                continue;
            }

            if ($this->holeriteRepo->findOneByCompetenciaAndFuncionario($competencia, $func)) {
                continue;
            }

            $inss = round($bruto * 0.075, 2);
            $irrf = round(max(0, ($bruto - $inss) * 0.075), 2);
            $fgts = round($bruto * 0.08, 2);
            $liquido = round($bruto - $inss - $irrf, 2);

            $hol = new RhFolhaHolerite();
            $hol->setCompetencia($competencia);
            $hol->setFuncionario($func);
            $hol->setSalarioBruto(number_format($bruto, 2, '.', ''));
            $hol->setInss(number_format($inss, 2, '.', ''));
            $hol->setIrrf(number_format($irrf, 2, '.', ''));
            $hol->setFgts(number_format($fgts, 2, '.', ''));
            $hol->setLiquido(number_format($liquido, 2, '.', ''));

            $this->em->persist($hol);
            $generated[] = $hol;
        }

        if ($generated !== []) {
            $this->em->flush();
            $this->audit->log($empresa, $actor, 'folha_legal', 'gerar_holerites', 'rh_folha_competencia', $competencia->getId(), [
                'quantidade' => \count($generated),
            ]);
        }

        return $generated;
    }

    public function listHoleritesForCompetencia(RhFolhaCompetencia $competencia): array
    {
        return $this->holeriteRepo->createQueryBuilder('h')
            ->andWhere('h.competencia = :competencia')
            ->setParameter('competencia', $competencia)
            ->orderBy('h.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
