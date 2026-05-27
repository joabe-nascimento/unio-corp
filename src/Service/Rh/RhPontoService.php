<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhPontoRegistro;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhPontoRegistroRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhPontoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhPontoRegistroRepository $repo,
        private RhAuditService $audit,
    ) {}

    public function registrarBatida(
        Empresa $empresa,
        Funcionario $funcionario,
        string $tipo,
        string $origem = RhPontoRegistro::ORIGEM_WEB,
        ?string $observacao = null,
        ?User $actor = null,
    ): RhPontoRegistro {
        if ($funcionario->getEmpresa()?->getId() !== $empresa->getId()) {
            throw new RhProcessException('Funcionário inválido para esta empresa.');
        }
        if (!\in_array($tipo, [RhPontoRegistro::TIPO_ENTRADA, RhPontoRegistro::TIPO_SAIDA], true)) {
            throw new RhProcessException('Tipo de batida inválido.');
        }

        $registro = new RhPontoRegistro();
        $registro->setEmpresa($empresa);
        $registro->setFuncionario($funcionario);
        $registro->setTipo($tipo);
        $registro->setOrigem($origem);
        $registro->setObservacao($observacao !== '' ? $observacao : null);

        $this->em->persist($registro);
        $this->em->flush();

        $this->audit->log($empresa, $actor, 'ponto', 'registrar_batida', 'rh_ponto_registro', $registro->getId(), [
            'tipo' => $tipo,
        ]);

        return $registro;
    }

    /** @return list<RhPontoRegistro> */
    public function listByFuncionarioAndDate(Funcionario $funcionario, \DateTimeImmutable $date): array
    {
        return $this->repo->findByFuncionarioAndDate($funcionario, $date);
    }
}
