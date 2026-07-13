<?php

namespace App\Tests\Service\PosOperatorio;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicAtendimento;
use App\Entity\ClinicGuiaItem;
use App\Entity\ClinicGuiaTiss;
use App\Entity\ClinicConvenio;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Repository\ClinicAtendimentoRepository;
use App\Repository\ClinicContaRepository;
use App\Repository\ClinicConvenioRepository;
use App\Repository\ClinicGuiaTissRepository;
use App\Service\PosOperatorio\ClinicAgendaService;
use App\Service\PosOperatorio\ClinicContaService;
use App\Service\PosOperatorio\ClinicGuiaTissService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ClinicExitMvpPhaseTest extends TestCase
{
    public function testPreEnvioChecklistRequiresTussAndAns(): void
    {
        $empresa = (new Empresa())->setNome('Clinica')->setCnpj('11.111.111/0001-11');
        $paciente = (new PosOperatorioPaciente())->setEmpresa($empresa)->setCodigo('PO-1')->setNome('Ana');
        $convenio = (new ClinicConvenio())->setEmpresa($empresa)->setNome('Unimed');

        $guia = new ClinicGuiaTiss();
        $guia->setEmpresa($empresa);
        $guia->setPaciente($paciente);
        $guia->setConvenio($convenio);
        $guia->setNumeroGuia('G-1');
        $guia->setStatus(ClinicGuiaTiss::STATUS_RASCUNHO);

        $item = new ClinicGuiaItem();
        $item->setDescricao('Consulta');
        $item->setQuantidade(1);
        $item->setValorCentavos(10000);
        $guia->addItem($item);

        $service = new ClinicGuiaTissService(
            $this->createMock(ClinicGuiaTissRepository::class),
            $this->createMock(ClinicConvenioRepository::class),
            new ClinicContaService(
                $this->createMock(ClinicContaRepository::class),
                $this->createMock(EntityManagerInterface::class),
            ),
            $this->createMock(EntityManagerInterface::class),
        );

        $checks = $service->preEnvioChecklist($guia);
        $byId = [];
        foreach ($checks as $c) {
            $byId[$c['id']] = $c['ok'];
        }

        self::assertTrue($byId['numero']);
        self::assertTrue($byId['itens']);
        self::assertFalse($byId['tuss']);
        self::assertTrue($byId['valor']);
        self::assertTrue($byId['convenio']);
        self::assertFalse($byId['ans']);

        $item->setCodigoTuss('10101012');
        $convenio->setRegistroAns('123456');
        foreach ($service->preEnvioChecklist($guia) as $c) {
            self::assertTrue($c['ok'], $c['id']);
        }
    }

    public function testAgendaCannotMarkFaltouWithOpenAtendimento(): void
    {
        $empresa = (new Empresa())->setNome('Clinica')->setCnpj('22.222.222/0001-22');
        $paciente = (new PosOperatorioPaciente())->setEmpresa($empresa)->setCodigo('PO-2')->setNome('Joao');
        $agendamento = new ClinicAgendamento();
        $agendamento->setEmpresa($empresa);
        $agendamento->setPaciente($paciente);
        $agendamento->setInicio(new \DateTimeImmutable('tomorrow 09:00'));
        $agendamento->setFim(new \DateTimeImmutable('tomorrow 09:30'));
        $agendamento->setStatus(ClinicAgendamento::STATUS_EM_ATENDIMENTO);

        $atendimento = new ClinicAtendimento();
        $atendimento->setEmpresa($empresa);
        $atendimento->setPaciente($paciente);
        $atendimento->setAgendamento($agendamento);
        $atendimento->setStatus(ClinicAtendimento::STATUS_EM_ANDAMENTO);

        $repo = $this->createMock(ClinicAtendimentoRepository::class);
        $repo->method('findOneByAgendamento')->willReturn($atendimento);

        $service = (new \ReflectionClass(ClinicAgendaService::class))->newInstanceWithoutConstructor();
        $ref = new \ReflectionClass($service);
        $prop = $ref->getProperty('atendimentos');
        $prop->setAccessible(true);
        $prop->setValue($service, $repo);

        $method = $ref->getMethod('assertStatusChange');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($service, $agendamento, $empresa, ClinicAgendamento::STATUS_FALTOU);
    }
}
