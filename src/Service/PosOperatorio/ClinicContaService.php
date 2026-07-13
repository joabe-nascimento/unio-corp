<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicAtendimento;
use App\Entity\ClinicConta;
use App\Entity\Empresa;
use App\Repository\ClinicContaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicContaService
{
    public function __construct(
        private ClinicContaRepository $contas,
        private EntityManagerInterface $em,
    ) {}

    public function ensureFromAtendimento(Empresa $empresa, ClinicAtendimento $atendimento): ClinicConta
    {
        if ($atendimento->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Atendimento fora do escopo.');
        }

        $existing = $this->contas->findOneByAtendimento($empresa, $atendimento);
        if ($existing !== null) {
            return $existing;
        }

        $byAgenda = $this->contas->findOneByAgendamento($empresa, $atendimento->getAgendamento());
        if ($byAgenda !== null) {
            if ($byAgenda->getAtendimento() === null) {
                $byAgenda->setAtendimento($atendimento);
                $byAgenda->touch();
                $this->em->flush();
            }

            return $byAgenda;
        }

        $titulo = $atendimento->getAgendamento()->getTitulo() ?: 'Consulta';
        $conta = new ClinicConta();
        $conta->setEmpresa($empresa);
        $conta->setAgendamento($atendimento->getAgendamento());
        $conta->setAtendimento($atendimento);
        $conta->setPaciente($atendimento->getPaciente());
        $conta->setTipo(ClinicConta::TIPO_PARTICULAR);
        $conta->setStatus(ClinicConta::STATUS_ABERTO);
        $conta->setDescricao('Atendimento: '.$titulo);

        $this->em->persist($conta);
        $this->em->flush();

        return $conta;
    }

    /** @return list<ClinicConta> */
    public function list(Empresa $empresa, ?string $status = null): array
    {
        return $this->contas->findByEmpresaAndStatus($empresa, $status);
    }

    public function countList(Empresa $empresa, ?string $status = null): int
    {
        return $this->contas->countByEmpresaAndStatus($empresa, $status);
    }

    public function listLimit(): int
    {
        return ClinicContaRepository::LIST_LIMIT;
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicConta
    {
        return $this->contas->findOneByEmpresa($empresa, $id);
    }

    public function markPago(ClinicConta $conta, Empresa $empresa, ?int $valorCentavos = null): ClinicConta
    {
        $this->assertScope($conta, $empresa);
        if (!$conta->isAberto()) {
            throw new \InvalidArgumentException('Conta não está aberta.');
        }
        if ($conta->getTipo() === ClinicConta::TIPO_CONVENIO) {
            throw new \InvalidArgumentException('Conta de convênio: use a guia TISS.');
        }

        if ($valorCentavos !== null) {
            if ($valorCentavos <= 0) {
                throw new \InvalidArgumentException('Informe um valor pago maior que zero.');
            }
            $conta->setValorCentavos($valorCentavos);
        } elseif ($conta->getValorCentavos() === null || $conta->getValorCentavos() <= 0) {
            throw new \InvalidArgumentException('Informe o valor pago.');
        }

        $conta->setTipo(ClinicConta::TIPO_PARTICULAR);
        $conta->setStatus(ClinicConta::STATUS_PAGO);
        $conta->setPagoEm(new \DateTimeImmutable());
        $conta->touch();
        $this->em->flush();

        return $conta;
    }

    public function markPagoFromGuia(ClinicConta $conta, Empresa $empresa, ?int $valorCentavos = null): ClinicConta
    {
        $this->assertScope($conta, $empresa);
        if (!$conta->isAberto()) {
            throw new \InvalidArgumentException('Conta não está aberta.');
        }
        if ($valorCentavos === null || $valorCentavos <= 0) {
            throw new \InvalidArgumentException('Guia sem valor: informe valores nos itens.');
        }
        $conta->setValorCentavos($valorCentavos);
        $conta->setTipo(ClinicConta::TIPO_CONVENIO);
        $conta->setStatus(ClinicConta::STATUS_PAGO);
        $conta->setPagoEm(new \DateTimeImmutable());
        $conta->touch();

        return $conta;
    }

    public function markGlosado(ClinicConta $conta, Empresa $empresa): ClinicConta
    {
        $this->assertScope($conta, $empresa);
        if (!$conta->isAberto()) {
            throw new \InvalidArgumentException('Conta não está aberta.');
        }
        $conta->setStatus(ClinicConta::STATUS_GLOSADO);
        $conta->setPagoEm(null);
        $conta->touch();

        return $conta;
    }

    public function reabrirAposGlosa(ClinicConta $conta, Empresa $empresa): ClinicConta
    {
        $this->assertScope($conta, $empresa);
        if ($conta->getStatus() !== ClinicConta::STATUS_GLOSADO) {
            throw new \InvalidArgumentException('Só contas glosadas podem ser reabertas.');
        }

        $conta->setStatus(ClinicConta::STATUS_ABERTO);
        $conta->setPagoEm(null);
        $conta->touch();

        return $conta;
    }

    public function cancelFromGuia(ClinicConta $conta, Empresa $empresa): ClinicConta
    {
        $this->assertScope($conta, $empresa);
        if (!$conta->isAberto()) {
            throw new \InvalidArgumentException('Conta não está aberta.');
        }
        $conta->setStatus(ClinicConta::STATUS_CANCELADO);
        $conta->setPagoEm(null);
        $conta->touch();

        return $conta;
    }

    public function markCortesia(ClinicConta $conta, Empresa $empresa): ClinicConta
    {
        $this->assertScope($conta, $empresa);
        if (!$conta->isAberto()) {
            throw new \InvalidArgumentException('Conta não está aberta.');
        }
        if ($conta->getTipo() === ClinicConta::TIPO_CONVENIO) {
            throw new \InvalidArgumentException('Conta de convênio: use a guia TISS.');
        }

        $conta->setTipo(ClinicConta::TIPO_CORTESIA);
        $conta->setStatus(ClinicConta::STATUS_PAGO);
        $conta->setValorCentavos(0);
        $conta->setPagoEm(new \DateTimeImmutable());
        $conta->touch();
        $this->em->flush();

        return $conta;
    }

    public function cancel(ClinicConta $conta, Empresa $empresa): ClinicConta
    {
        $this->assertScope($conta, $empresa);
        if (!$conta->isAberto()) {
            throw new \InvalidArgumentException('Conta não está aberta.');
        }
        if ($conta->getTipo() === ClinicConta::TIPO_CONVENIO) {
            throw new \InvalidArgumentException('Conta de convênio: use a guia TISS.');
        }

        $conta->setStatus(ClinicConta::STATUS_CANCELADO);
        $conta->touch();
        $this->em->flush();

        return $conta;
    }

    public function assertScopePublic(ClinicConta $conta, Empresa $empresa): void
    {
        $this->assertScope($conta, $empresa);
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            ClinicConta::STATUS_ABERTO => 'Aberto',
            ClinicConta::STATUS_PAGO => 'Pago',
            ClinicConta::STATUS_CANCELADO => 'Cancelado',
            ClinicConta::STATUS_GLOSADO => 'Glosado',
        ];
    }

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return [
            ClinicConta::TIPO_PARTICULAR => 'Particular',
            ClinicConta::TIPO_CORTESIA => 'Cortesia',
            ClinicConta::TIPO_CONVENIO => 'Convênio',
        ];
    }

    private function assertScope(ClinicConta $conta, Empresa $empresa): void
    {
        if ($conta->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Conta fora do escopo.');
        }
    }
}
