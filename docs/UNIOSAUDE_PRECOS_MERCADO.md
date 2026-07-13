# Unio Saúde — referência de preços de mercado

**Uso:** material interno / comercial. **Não exibir** na landing pública.

**Atualizado:** jul/2026

## Posicionamento Unio

A Unio cobra **por clínica** (com limite de pacientes ativos), não por usuário ou profissional. O preço acompanha o escopo entregue (continuidade pós-alta + operação clínica + faturamento TISS), sem competir no piso de mercado.

## Planos Unio (catálogo público)

Fonte de verdade: `src/PosOperatorio/ClinicCommercialPlans.php` · landing `#planos`.

| Plano | Preço | Pacientes | Escopo (resumo) |
|-------|-------|-----------|-----------------|
| **Essencial** | R$ 189 / clínica / mês | ≤ 100 | Pacientes, protocolos, alertas, portal |
| **Clínica** (recomendado) | R$ 279 / clínica / mês | ≤ 500 | + agenda, atendimento, contas, docs do paciente, TISS |
| **Rede** | Sob consulta (~a partir de R$ 499) | ≤ 2000 / cotação | White-label, multi-unidade, limites altos |

## Comparativo de mercado (referência jul/2026)

Valores públicos / comparativos de mercado consultados em jul/2026. Podem variar por pacote, add-ons e região.

| Player | Modelo típico | Referência aproximada |
|--------|---------------|------------------------|
| **OnDoctor** | Por usuário | ~R$ 79,90 / usuário |
| **Feegow** | Por profissional | ~R$ 129+ / profissional |
| **Clinicorp** | Por clínica | ~R$ 127 / clínica |

**Leitura:** OnDoctor, Feegow e Clinicorp cobram por usuário, profissional ou clínica com outro pacote de módulos. A Unio cobra por clínica com **limites claros de pacientes** e enfatiza continuidade pós-alta + caminho atendimento → conta → guia TISS → lote/XML.

## Fontes de consulta

- Feegow Clinic — feegowclinic.com.br  
- iClinic — iclinic.com.br  
- Clinicorp / Triagefy / Operatório — posicionamento CRM e cirúrgico  
- Comparativos públicos Feegow × Clinicorp (gestão 2025/2026)  
- Roadmap interno: [UNIOSAUDE_IDEIAS_ROADMAP.md](UNIOSAUDE_IDEIAS_ROADMAP.md)

## Nota sobre a landing

A frase de rodapé dos planos (“Valores de referência jul/2026. OnDoctor, Feegow…”) foi **retirada da landing** em jul/2026 para não expor comparativo competitivo na página pública. Manter esta referência apenas em docs internos e no comentário de `ClinicCommercialPlans.php`.
