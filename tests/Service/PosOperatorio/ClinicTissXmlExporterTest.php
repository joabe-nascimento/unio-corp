<?php

namespace App\Tests\Service\PosOperatorio;

use App\Entity\ClinicConvenio;
use App\Entity\ClinicGuiaItem;
use App\Entity\ClinicGuiaTiss;
use App\Entity\ClinicLoteTiss;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Service\PosOperatorio\ClinicTissXmlExporter;
use PHPUnit\Framework\TestCase;

final class ClinicTissXmlExporterTest extends TestCase
{
    public function testExportGuiaContainsProceduresTotalsAndMd5Hash(): void
    {
        $guia = $this->guiaCompleta('G-100', '10101012', 'Consulta em consultorio', 1, 15000, 'ABC123');

        $xml = (new ClinicTissXmlExporter())->exportGuia($guia);

        self::assertStringContainsString('mensagemTISS', $xml);
        self::assertStringContainsString('ENVIO_LOTE_GUIAS', $xml);
        self::assertStringContainsString('3.05.00', $xml);
        self::assertStringContainsString('guiaSP-SADT', $xml);
        self::assertStringContainsString('procedimentosSolicitados', $xml);
        self::assertStringContainsString('procedimentosExecutados', $xml);
        self::assertStringContainsString('10101012', $xml);
        self::assertStringContainsString('Consulta em consultorio', $xml);
        self::assertStringContainsString('150.00', $xml);
        self::assertStringContainsString('valorDiarias', $xml);
        self::assertStringContainsString('12345678000190', $xml);
        self::assertStringContainsString('G-100', $xml);
        self::assertStringContainsString('ABC123', $xml);
        self::assertStringContainsString('profissionalSolicitante', $xml);
        self::assertStringContainsString('<ans:CNES>', $xml);
        self::assertMatchesRegularExpression('#<ans:hash>[a-f0-9]{32}</ans:hash>#', $xml);
        self::assertStringNotContainsString('numeroCNS', $xml);
    }

    public function testExportLoteIncludesGuiaItems(): void
    {
        $empresa = $this->empresa();
        $convenio = $this->convenio($empresa, '999999');
        $guia = $this->guiaCompleta('G-200', '20101015', 'Curativo simples', 2, 4500);
        $guia->setEmpresa($empresa);
        $guia->setConvenio($convenio);

        $lote = new ClinicLoteTiss();
        $lote->setEmpresa($empresa);
        $lote->setConvenio($convenio);
        $lote->setNumero('L20260701');
        $lote->setCompetencia('2026-07');
        $lote->setStatus(ClinicLoteTiss::STATUS_FECHADO);
        $lote->addGuia($guia);

        $xml = (new ClinicTissXmlExporter())->exportLote($lote);

        self::assertStringContainsString('L20260701', $xml);
        self::assertStringContainsString('G-200', $xml);
        self::assertStringContainsString('20101015', $xml);
        self::assertStringContainsString('90.00', $xml);
        self::assertStringContainsString('999999', $xml);
        self::assertMatchesRegularExpression('#<ans:hash>[a-f0-9]{32}</ans:hash>#', $xml);
    }

    public function testExportRejectsMissingTussCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('código TUSS');

        $guia = $this->guiaCompleta('G-300', '', 'Sem codigo', 1, 1000);
        $guia->getItens()->first()->setCodigoTuss(null);

        (new ClinicTissXmlExporter())->exportGuia($guia);
    }

    public function testExportRejectsMissingAns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Registro ANS');

        $empresa = $this->empresa();
        $convenio = $this->convenio($empresa, null);
        $guia = $this->guiaCompleta('G-400', '10101012', 'Consulta', 1, 1000);
        $guia->setEmpresa($empresa);
        $guia->setConvenio($convenio);

        (new ClinicTissXmlExporter())->exportGuia($guia);
    }

    public function testExportLoteRequiresGuias(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $empresa = $this->empresa();
        $convenio = $this->convenio($empresa, '123456');

        $lote = new ClinicLoteTiss();
        $lote->setEmpresa($empresa);
        $lote->setConvenio($convenio);
        $lote->setNumero('L1');
        $lote->setCompetencia('2026-07');

        (new ClinicTissXmlExporter())->exportLote($lote);
    }

    public function testLoteStatusHelpers(): void
    {
        $lote = new ClinicLoteTiss();
        self::assertTrue($lote->isAberto());
        self::assertFalse($lote->canExportXml());

        $lote->setStatus(ClinicLoteTiss::STATUS_FECHADO);
        self::assertFalse($lote->isAberto());
    }

    private function empresa(): Empresa
    {
        $empresa = new Empresa();
        $empresa->setNome('Clinica Demo');
        $empresa->setCnpj('12.345.678/0001-90');

        return $empresa;
    }

    private function convenio(Empresa $empresa, ?string $ans): ClinicConvenio
    {
        $convenio = new ClinicConvenio();
        $convenio->setNome('Unimed');
        $convenio->setRegistroAns($ans);
        $convenio->setEmpresa($empresa);

        return $convenio;
    }

    private function guiaCompleta(
        string $numero,
        string $tuss,
        string $descricao,
        int $qtd,
        int $centavos,
        ?string $senha = null,
    ): ClinicGuiaTiss {
        $empresa = $this->empresa();
        $convenio = $this->convenio($empresa, '123456');

        $paciente = new PosOperatorioPaciente();
        $paciente->setNome('Ana Costa');
        $paciente->setCodigo('PO-0001');
        $paciente->setCpf('529.982.247-25');
        $paciente->setEmpresa($empresa);

        $guia = new ClinicGuiaTiss();
        $guia->setEmpresa($empresa);
        $guia->setConvenio($convenio);
        $guia->setPaciente($paciente);
        $guia->setNumeroGuia($numero);
        if ($senha !== null) {
            $guia->setSenhaAutorizacao($senha);
        }

        $item = new ClinicGuiaItem();
        $item->setCodigoTuss($tuss !== '' ? $tuss : null);
        $item->setDescricao($descricao);
        $item->setQuantidade($qtd);
        $item->setValorCentavos($centavos);
        $guia->addItem($item);

        return $guia;
    }
}
