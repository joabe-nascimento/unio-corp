<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhProvisao;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhProvisaoRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhProvisaoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhProvisaoRepository $repo,
        private FuncionarioRepository $funcionarioRepo,
        private RhAuditService $audit,
    ) {}

    /** @return list<RhProvisao> */
    public function listForEmpresa(Empresa $empresa, ?string $referencia = null): array
    {
        return $this->repo->findForEmpresa($empresa, $referencia);
    }

    /**
     * Calcula provisões simplificadas (stub) para a competência.
     *
     * @return list<RhProvisao>
     */
    public function calculate(Empresa $empresa, string $referencia, ?User $actor = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $referencia)) {
            throw new RhProcessException('Referência inválida. Use AAAA-MM.');
        }

        $ativos = $this->funcionarioRepo->findBy(['empresa' => $empresa, 'status' => 'ATIVO']);
        $folhaBase = 0.0;
        foreach ($ativos as $f) {
            $folhaBase += (float) ($f->getSalario() ?? 0);
        }

        $tipos = [
            RhProvisao::TIPO_FERIAS => 0.1111,
            RhProvisao::TIPO_DECIMO => 0.0833,
            RhProvisao::TIPO_ENCARGOS => 0.20,
        ];

        $result = [];
        foreach ($tipos as $tipo => $pct) {
            $valor = round($folhaBase * $pct, 2);
            $prov = $this->repo->findOneByEmpresaRefTipo($empresa, $referencia, $tipo);
            if (!$prov) {
                $prov = new RhProvisao();
                $prov->setEmpresa($empresa);
                $prov->setReferencia($referencia);
                $prov->setTipo($tipo);
                $this->em->persist($prov);
            }
            $prov->setValor(number_format($valor, 2, '.', ''));
            $prov->setStatus(RhProvisao::STATUS_ABERTA);
            $result[] = $prov;
        }

        $this->em->flush();
        $this->audit->log($empresa, $actor, 'contabilidade', 'calcular_provisoes', null, null, [
            'referencia' => $referencia,
        ]);

        return $result;
    }
}
