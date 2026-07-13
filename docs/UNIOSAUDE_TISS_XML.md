# Unio Saúde — XML TISS (remessa)

**Atualizado:** jul/2026  
**Implementação:** `src/Service/PosOperatorio/ClinicTissXmlExporter.php`

## O que foi completado

O exportador gera `mensagemTISS` no padrão ANS de **comunicação prestador → operadora**, versão de referência **3.05.00**, com:

| Bloco | Conteúdo |
|-------|----------|
| `cabecalho` | `ENVIO_LOTE_GUIAS`, sequencial, data/hora, CNPJ prestador, registro ANS destino, `Padrao` 3.05.00 |
| `prestadorParaOperadora` / `loteGuias` | Número do lote + `guiasTISS` |
| `guiaSP-SADT` | Cabeçalho, beneficiário, solicitação, procedimentos solicitados, solicitante, executante+CNES, atendimento, procedimentos executados, `valorTotal` completo |
| `epilogo` / `hash` | **MD5** do XML com `<ans:hash></ans:hash>` vazio (regra clássica ANS) |

### Validações antes de exportar

- CNPJ da empresa com **14 dígitos**
- Registro ANS do convênio com **5–6 dígitos**
- Guia com itens, número, valor total > 0
- Cada item com **código TUSS** e valor

### Campos preenchidos na SP/SADT

- Solicitação: data, caráter eletivo, indicação clínica  
- Profissional solicitante: nome (médico do atendimento/paciente), conselho CRM (`06`), número/UF/CBOS com defaults  
- Executante: CNPJ + nome + CNES (derivado do CNPJ enquanto não houver cadastro CNES)  
- Procedimentos: tabela `22`, data/hora, via/técnica, redução `1.00`, valores unitário/total  
- Totais: procedimentos + diárias/taxas/materiais/medicamentos/OPME/gases zerados + total geral  

## Limitações conscientes (ajuste fino por operadora)

1. **Versão XSD** — algumas operadoras exigem 3.03.x, 3.04.x ou 4.x; o `schemaLocation` aponta `tissV3_05_00.xsd`.  
2. **CNES / CRM reais** — ainda não há campos dedicados em `Empresa`/`User`; defaults evitam XML incompleto, mas a operadora pode rejeitar.  
3. **Número da carteira** — usa o código do paciente Unio; o ideal é a carteira do plano.  
4. **CNS** — não é preenchido com CPF (erro comum); só informe CNS real quando existir.  
5. **Tipo de guia** — hoje só **SP/SADT**; consulta pura / internação / honorário são evoluções.  
6. **Encoding** — UTF-8; portais antigos podem pedir ISO-8859-1.

## Como usar

1. Cadastre **CNPJ** da clínica e **registro ANS** no convênio.  
2. Na guia, itens com código TUSS (catálogo ou manual).  
3. Monte o **lote**, feche e baixe o XML — ou baixe XML avulso na guia.  
4. Valide no portal TISS / validador da operadora; ajuste versão ou campos se o XSD recusar.

## Testes

`tests/Service/PosOperatorio/ClinicTissXmlExporterTest.php`
