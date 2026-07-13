<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicGuiaTiss;
use App\Entity\ClinicLoteTiss;
use App\Entity\Empresa;
use App\Entity\User;

/**
 * Exporta mensagemTISS (prestador → operadora) alinhada ao componente de comunicação TISS.
 *
 * Versão de referência: 3.05.00 (guia SP/SADT em loteGuias).
 * Hash do epílogo: MD5 do XML sem o valor de <ans:hash>, conforme padrão ANS.
 *
 * Campos de profissional (CRM/UF/CBOS) e CNES usam defaults quando a clínica ainda
 * não cadastrou dados TISS do prestador — ajuste fino por operadora pode ser necessário.
 *
 * @see docs/UNIOSAUDE_TISS_XML.md
 */
final class ClinicTissXmlExporter
{
    public const VERSAO_PADRAO = '3.05.00';
    public const NS = 'http://www.ans.gov.br/padroes/tiss/schemas';

    /** Tabela 22 = Procedimentos e eventos em saúde (TUSS). */
    public const TABELA_TUSS = '22';

    /** tipoAtendimento 04 = consulta; 05 = SP/SADT (ambulatorial). */
    public const TIPO_ATENDIMENTO_SPSADT = '05';

    /** indicacaoAcidente 9 = Não acidente. */
    public const INDICACAO_ACIDENTE_NAO = '9';

    /** caraterAtendimento 1 = Eletiva. */
    public const CARATER_ELETIVA = '1';

    public function exportLote(ClinicLoteTiss $lote): string
    {
        if ($lote->getGuias()->isEmpty()) {
            throw new \InvalidArgumentException('Lote sem guias para exportar.');
        }

        $empresa = $lote->getEmpresa();
        $convenio = $lote->getConvenio();
        $cnpj = $this->requireCnpj($empresa);
        $registroAns = $this->requireRegistroAns($convenio->getRegistroAns());

        foreach ($lote->getGuias() as $guia) {
            $this->assertGuiaExportavel($guia);
        }

        $now = new \DateTimeImmutable();
        $dom = $this->newDocument();
        $root = $this->appendRoot($dom);
        $this->appendCabecalho(
            $dom,
            $root,
            (string) ($lote->getId() ?? 1),
            $now,
            $cnpj,
            $registroAns,
        );

        $corpo = $dom->createElement('ans:prestadorParaOperadora');
        $root->appendChild($corpo);
        $loteGuias = $dom->createElement('ans:loteGuias');
        $corpo->appendChild($loteGuias);
        $loteGuias->appendChild($dom->createElement('ans:numeroLote', $this->safeText($lote->getNumero(), 12)));

        $guiasTiss = $dom->createElement('ans:guiasTISS');
        $loteGuias->appendChild($guiasTiss);
        foreach ($lote->getGuias() as $guia) {
            $guiasTiss->appendChild($this->buildGuiaSpSadt($dom, $guia, $empresa, $registroAns, $cnpj));
        }

        $this->appendEpilogoWithMd5($dom, $root);

        return $this->finalize($dom);
    }

    public function exportGuia(ClinicGuiaTiss $guia): string
    {
        $this->assertGuiaExportavel($guia);

        $empresa = $guia->getEmpresa();
        $convenio = $guia->getConvenio();
        $cnpj = $this->requireCnpj($empresa);
        $registroAns = $this->requireRegistroAns($convenio->getRegistroAns());

        $now = new \DateTimeImmutable();
        $dom = $this->newDocument();
        $root = $this->appendRoot($dom);
        $this->appendCabecalho(
            $dom,
            $root,
            (string) ($guia->getId() ?? 1),
            $now,
            $cnpj,
            $registroAns,
        );

        $corpo = $dom->createElement('ans:prestadorParaOperadora');
        $root->appendChild($corpo);
        $loteGuias = $dom->createElement('ans:loteGuias');
        $corpo->appendChild($loteGuias);
        $loteGuias->appendChild($dom->createElement(
            'ans:numeroLote',
            $this->safeText('G'.preg_replace('/\D+/', '', $guia->getNumeroGuia()), 12) ?: 'G00000000001',
        ));

        $guiasTiss = $dom->createElement('ans:guiasTISS');
        $loteGuias->appendChild($guiasTiss);
        $guiasTiss->appendChild($this->buildGuiaSpSadt($dom, $guia, $empresa, $registroAns, $cnpj));

        $this->appendEpilogoWithMd5($dom, $root);

        return $this->finalize($dom);
    }

    /**
     * @return list<string>
     */
    public function validateGuia(ClinicGuiaTiss $guia): array
    {
        $errors = [];
        try {
            $this->requireCnpj($guia->getEmpresa());
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }
        try {
            $this->requireRegistroAns($guia->getConvenio()->getRegistroAns());
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }
        try {
            $this->assertGuiaExportavel($guia);
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    private function assertGuiaExportavel(ClinicGuiaTiss $guia): void
    {
        if ($guia->getItens()->isEmpty()) {
            throw new \InvalidArgumentException(sprintf('Guia %s sem itens.', $guia->getNumeroGuia()));
        }
        if (trim($guia->getNumeroGuia()) === '') {
            throw new \InvalidArgumentException('Número da guia do prestador é obrigatório.');
        }
        if ($guia->totalCentavos() <= 0) {
            throw new \InvalidArgumentException(sprintf('Guia %s sem valor nos itens.', $guia->getNumeroGuia()));
        }

        foreach ($guia->getItens() as $item) {
            $codigo = preg_replace('/\D+/', '', (string) ($item->getCodigoTuss() ?? ''));
            if ($codigo === null || $codigo === '') {
                throw new \InvalidArgumentException(sprintf(
                    'Guia %s: item “%s” precisa de código TUSS para o XML.',
                    $guia->getNumeroGuia(),
                    $item->getDescricao(),
                ));
            }
            if ($item->getValorCentavos() === null || $item->getValorCentavos() < 0) {
                throw new \InvalidArgumentException(sprintf(
                    'Guia %s: item “%s” precisa de valor.',
                    $guia->getNumeroGuia(),
                    $item->getDescricao(),
                ));
            }
        }
    }

    private function requireCnpj(Empresa $empresa): string
    {
        $cnpj = preg_replace('/\D+/', '', (string) ($empresa->getCnpj() ?? '')) ?? '';
        if (\strlen($cnpj) !== 14) {
            throw new \InvalidArgumentException('CNPJ do prestador (empresa) deve ter 14 dígitos para exportar o XML TISS.');
        }

        return $cnpj;
    }

    private function requireRegistroAns(?string $raw): string
    {
        $ans = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if (\strlen($ans) < 5 || \strlen($ans) > 6) {
            throw new \InvalidArgumentException('Registro ANS do convênio é obrigatório (5 ou 6 dígitos) para o XML TISS.');
        }

        return str_pad($ans, 6, '0', \STR_PAD_LEFT);
    }

    private function newDocument(): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;

        return $dom;
    }

    private function appendRoot(\DOMDocument $dom): \DOMElement
    {
        $root = $dom->createElementNS(self::NS, 'ans:mensagemTISS');
        $dom->appendChild($root);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ans', self::NS);
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', self::NS.' tissV3_05_00.xsd');

        return $root;
    }

    private function appendCabecalho(
        \DOMDocument $dom,
        \DOMElement $root,
        string $sequencial,
        \DateTimeImmutable $now,
        string $cnpj,
        string $registroAns,
    ): void {
        $cabecalho = $dom->createElement('ans:cabecalho');
        $root->appendChild($cabecalho);

        $ident = $dom->createElement('ans:identificacaoTransacao');
        $cabecalho->appendChild($ident);
        $ident->appendChild($dom->createElement('ans:tipoTransacao', 'ENVIO_LOTE_GUIAS'));
        $ident->appendChild($dom->createElement('ans:sequencialTransacao', mb_substr(preg_replace('/\D+/', '', $sequencial) ?: '1', 0, 12)));
        $ident->appendChild($dom->createElement('ans:dataRegistroTransacao', $now->format('Y-m-d')));
        $ident->appendChild($dom->createElement('ans:horaRegistroTransacao', $now->format('H:i:s')));

        $origem = $dom->createElement('ans:origem');
        $cabecalho->appendChild($origem);
        $prestador = $dom->createElement('ans:identificacaoPrestador');
        $origem->appendChild($prestador);
        $prestador->appendChild($dom->createElement('ans:CNPJ', $cnpj));

        $destino = $dom->createElement('ans:destino');
        $cabecalho->appendChild($destino);
        $destino->appendChild($dom->createElement('ans:registroANS', $registroAns));

        $cabecalho->appendChild($dom->createElement('ans:Padrao', self::VERSAO_PADRAO));
    }

    private function buildGuiaSpSadt(
        \DOMDocument $dom,
        ClinicGuiaTiss $guia,
        Empresa $empresa,
        string $registroAns,
        string $cnpj,
    ): \DOMElement {
        $node = $dom->createElement('ans:guiaSP-SADT');
        $dataRef = $guia->getCriadoEm();
        $atendimento = $guia->getAtendimento();
        if ($atendimento !== null && $atendimento->getFinalizadoEm() !== null) {
            $dataRef = $atendimento->getFinalizadoEm();
        }

        // cabecalhoGuia
        $cab = $dom->createElement('ans:cabecalhoGuia');
        $node->appendChild($cab);
        $cab->appendChild($dom->createElement('ans:registroANS', $registroAns));
        $cab->appendChild($dom->createElement('ans:numeroGuiaPrestador', $this->safeText($guia->getNumeroGuia(), 20)));
        if ($guia->getSenhaAutorizacao()) {
            $cab->appendChild($dom->createElement('ans:dataAutorizacao', $dataRef->format('Y-m-d')));
            $cab->appendChild($dom->createElement('ans:senha', $this->safeText($guia->getSenhaAutorizacao(), 20)));
            $cab->appendChild($dom->createElement('ans:dataValidadeSenha', $dataRef->modify('+30 days')->format('Y-m-d')));
        }

        // dadosBeneficiario
        $paciente = $guia->getPaciente();
        $dadosBenef = $dom->createElement('ans:dadosBeneficiario');
        $node->appendChild($dadosBenef);
        $carteira = $this->safeText($paciente->getCodigo(), 20);
        $dadosBenef->appendChild($dom->createElement('ans:numeroCarteira', $carteira !== '' ? $carteira : 'NAOINFORMADO'));
        $dadosBenef->appendChild($dom->createElement('ans:atendimentoRN', 'N'));
        $dadosBenef->appendChild($dom->createElement('ans:nomeBeneficiario', $this->safeText($paciente->getNome(), 70)));

        // dadosSolicitacao
        $dadosSolic = $dom->createElement('ans:dadosSolicitacao');
        $node->appendChild($dadosSolic);
        $dadosSolic->appendChild($dom->createElement('ans:dataSolicitacao', $dataRef->format('Y-m-d')));
        $dadosSolic->appendChild($dom->createElement('ans:caraterAtendimento', self::CARATER_ELETIVA));
        $dadosSolic->appendChild($dom->createElement('ans:indicacaoClinica', $this->safeText(
            $atendimento?->getQueixa() ?: ($paciente->getProcedimento() ?: 'Atendimento ambulatorial'),
            500,
        )));

        // dadosSolicitante
        $solicitante = $dom->createElement('ans:dadosSolicitante');
        $node->appendChild($solicitante);
        $contratadoSol = $dom->createElement('ans:contratadoSolicitante');
        $solicitante->appendChild($contratadoSol);
        $contratadoSol->appendChild($dom->createElement('ans:cnpjContratado', $cnpj));
        $contratadoSol->appendChild($dom->createElement('ans:nomeContratado', $this->safeText((string) ($empresa->getNome() ?? 'Prestador'), 70)));
        $profissional = $this->resolveProfissional($guia);
        $profNode = $dom->createElement('ans:profissionalSolicitante');
        $solicitante->appendChild($profNode);
        $profNode->appendChild($dom->createElement('ans:nomeProfissional', $profissional['nome']));
        $profNode->appendChild($dom->createElement('ans:conselhoProfissional', $profissional['conselho']));
        $profNode->appendChild($dom->createElement('ans:numeroConselhoProfissional', $profissional['numero']));
        $profNode->appendChild($dom->createElement('ans:UF', $profissional['uf']));
        $profNode->appendChild($dom->createElement('ans:CBOS', $profissional['cbos']));

        // dadosSolicitacao / procedimentosSolicitados (espelha executados)
        $procsSolic = $dom->createElement('ans:procedimentosSolicitados');
        // In TISS, procedimentosSolicitados often sits after dadosSolicitante in some versions;
        // for 3.05 SP/SADT execution guide, executed procedures are the main block.
        // We keep solicitados aligned with executados for operadoras that expect both.

        // dadosExecutante
        $exec = $dom->createElement('ans:dadosExecutante');
        $node->appendChild($exec);
        $contratadoExec = $dom->createElement('ans:contratadoExecutante');
        $exec->appendChild($contratadoExec);
        $contratadoExec->appendChild($dom->createElement('ans:cnpjContratado', $cnpj));
        $contratadoExec->appendChild($dom->createElement('ans:nomeContratado', $this->safeText((string) ($empresa->getNome() ?? 'Prestador'), 70)));
        $exec->appendChild($dom->createElement('ans:CNES', $this->resolveCnes($empresa)));

        // dadosAtendimento
        $dadosAtend = $dom->createElement('ans:dadosAtendimento');
        $node->appendChild($dadosAtend);
        $dadosAtend->appendChild($dom->createElement('ans:tipoAtendimento', self::TIPO_ATENDIMENTO_SPSADT));
        $dadosAtend->appendChild($dom->createElement('ans:indicacaoAcidente', self::INDICACAO_ACIDENTE_NAO));
        $dadosAtend->appendChild($dom->createElement('ans:tipoConsulta', '1')); // primeira

        // procedimentosExecutados (+ mirror solicitados)
        $procExec = $dom->createElement('ans:procedimentosExecutados');
        $node->appendChild($procExec);
        $seq = 1;
        foreach ($guia->getItens() as $item) {
            $codigo = preg_replace('/\D+/', '', (string) $item->getCodigoTuss()) ?: '00000000';
            $qtd = max(1, $item->getQuantidade());
            $unit = (int) ($item->getValorCentavos() ?? 0);
            $total = $unit * $qtd;

            $solicItem = $dom->createElement('ans:procedimentoSolicitado');
            $procsSolic->appendChild($solicItem);
            $solicProc = $dom->createElement('ans:procedimento');
            $solicItem->appendChild($solicProc);
            $solicProc->appendChild($dom->createElement('ans:codigoTabela', self::TABELA_TUSS));
            $solicProc->appendChild($dom->createElement('ans:codigoProcedimento', $this->safeText($codigo, 10)));
            $solicProc->appendChild($dom->createElement('ans:descricaoProcedimento', $this->safeText($item->getDescricao(), 150)));
            $solicItem->appendChild($dom->createElement('ans:quantidadeSolicitada', (string) $qtd));

            $row = $dom->createElement('ans:procedimentoExecutado');
            $procExec->appendChild($row);
            $row->appendChild($dom->createElement('ans:sequencialItem', (string) $seq++));
            $row->appendChild($dom->createElement('ans:dataExecucao', $dataRef->format('Y-m-d')));
            $row->appendChild($dom->createElement('ans:horaInicial', $dataRef->format('H:i:s')));
            $row->appendChild($dom->createElement('ans:horaFinal', $dataRef->modify('+30 minutes')->format('H:i:s')));
            $procNode = $dom->createElement('ans:procedimento');
            $row->appendChild($procNode);
            $procNode->appendChild($dom->createElement('ans:codigoTabela', self::TABELA_TUSS));
            $procNode->appendChild($dom->createElement('ans:codigoProcedimento', $this->safeText($codigo, 10)));
            $procNode->appendChild($dom->createElement('ans:descricaoProcedimento', $this->safeText($item->getDescricao(), 150)));
            $row->appendChild($dom->createElement('ans:quantidadeExecutada', (string) $qtd));
            $row->appendChild($dom->createElement('ans:viaAcesso', '1'));
            $row->appendChild($dom->createElement('ans:tecnicaUtilizada', '1'));
            $row->appendChild($dom->createElement('ans:reducaoAcrescimo', '1.00'));
            $row->appendChild($dom->createElement('ans:valorUnitario', $this->money($unit)));
            $row->appendChild($dom->createElement('ans:valorTotal', $this->money($total)));
        }

        // Insert procedimentosSolicitados after dadosSolicitacao (before solicitante would be ideal;
        // TISS order for guiaSP-SADT typically: cabecalho, beneficiario, solicitacao+procs, solicitante, executante, atendimento, executados, valorTotal)
        // We already created dadosSolicitacao earlier; append procsSolic as sibling after it by rebuilding order is hard.
        // Append after dadosSolicitacao node:
        if ($dadosSolic->nextSibling !== null) {
            $node->insertBefore($procsSolic, $dadosSolic->nextSibling);
        } else {
            $node->appendChild($procsSolic);
        }

        // valorTotal (breakdown completo — zeros quando não há diária/OPME/etc.)
        $valorTotal = $dom->createElement('ans:valorTotal');
        $node->appendChild($valorTotal);
        $procTotal = $this->money($guia->totalCentavos());
        $zero = '0.00';
        $valorTotal->appendChild($dom->createElement('ans:valorProcedimentos', $procTotal));
        $valorTotal->appendChild($dom->createElement('ans:valorDiarias', $zero));
        $valorTotal->appendChild($dom->createElement('ans:valorTaxasAlugueis', $zero));
        $valorTotal->appendChild($dom->createElement('ans:valorMateriais', $zero));
        $valorTotal->appendChild($dom->createElement('ans:valorMedicamentos', $zero));
        $valorTotal->appendChild($dom->createElement('ans:valorOPME', $zero));
        $valorTotal->appendChild($dom->createElement('ans:valorGasesMedicinais', $zero));
        $valorTotal->appendChild($dom->createElement('ans:valorTotalGeral', $procTotal));

        $obs = sprintf(
            'Unio Saude TISS %s · guia %s · status %s',
            self::VERSAO_PADRAO,
            $guia->getNumeroGuia(),
            $guia->getStatus(),
        );
        $node->appendChild($dom->createElement('ans:observacao', $this->safeText($obs, 500)));

        return $node;
    }

    /**
     * @return array{nome: string, conselho: string, numero: string, uf: string, cbos: string}
     */
    private function resolveProfissional(ClinicGuiaTiss $guia): array
    {
        $medico = $guia->getAtendimento()?->getMedico()
            ?? $guia->getPaciente()->getMedicoResponsavel();

        $nome = 'PROFISSIONAL NAO INFORMADO';
        if ($medico instanceof User) {
            $nome = trim((string) ($medico->getNome() ?? $medico->getUserIdentifier() ?? ''));
            if ($nome === '') {
                $nome = 'PROFISSIONAL NAO INFORMADO';
            }
        }

        return [
            'nome' => $this->safeText($nome, 70),
            // 06 = CRM (tabela de domínio TISS conselhoProfissional)
            'conselho' => '06',
            'numero' => '000000',
            'uf' => 'SP',
            // 225125 = Médico clínico (CBOS aproximado; operadora pode exigir outro)
            'cbos' => '225125',
        ];
    }

    private function resolveCnes(Empresa $empresa): string
    {
        // Sem campo CNES na empresa ainda: usa 7 dígitos derivados do CNPJ (placeholder explícito).
        $digits = preg_replace('/\D+/', '', (string) ($empresa->getCnpj() ?? '')) ?: '0000000';

        return mb_substr($digits, -7);
    }

    private function appendEpilogoWithMd5(\DOMDocument $dom, \DOMElement $root): void
    {
        $epilogo = $dom->createElement('ans:epilogo');
        $root->appendChild($epilogo);
        $hashNode = $dom->createElement('ans:hash', '');
        $epilogo->appendChild($hashNode);

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Falha ao serializar XML TISS.');
        }

        // Remove valor do hash (e a tag vazia) para calcular MD5 do restante da mensagem.
        $forHash = preg_replace('#<ans:hash>[^<]*</ans:hash>#', '<ans:hash></ans:hash>', $xml) ?? $xml;
        $md5 = md5($forHash);
        $hashNode->nodeValue = $md5;
    }

    private function finalize(\DOMDocument $dom): string
    {
        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Falha ao gerar XML TISS.');
        }

        return $xml;
    }

    private function money(int $centavos): string
    {
        return number_format(max(0, $centavos) / 100, 2, '.', '');
    }

    private function safeText(string $value, int $max = 70): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);

        return mb_substr($clean, 0, $max);
    }
}
