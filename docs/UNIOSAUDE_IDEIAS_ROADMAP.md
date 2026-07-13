# Unio Saúde — ideias e roadmap de produto

Documento de visão: o que a plataforma é, o que já existe, feedback de mercado, e o que implementar depois.  
Atualizado: jul/2026 (inclui conversa Joabe × Arthur × Luiz Fernando / União Médica).

---

## Ideia central (evoluída)

### Dor clínica que já resolvemos bem

A clínica **não pode “perder” o paciente depois da cirurgia**.

“Perder” não é sumir da ficha: é operar, mandar pra casa e **não acompanhar de perto** nos dias seguintes — sem saber se está bem, se respondeu, se precisava de ajuda. Muitas vezes a equipe só descobre no retorno ou quando já deu problema.

Pós-operatório, alertas, portal, carteirinha, guia e comprovante fecham esse buraco.

### Dor de mercado (Fernando / União Médica) — posicionamento de venda

O problema que o **cliente aceita pagar** hoje, na visão do Fernando, não é “só pós-op” isolado:

> As clínicas possuem **muitos sistemas: um pra cada coisa**.

O melhor é um **sistema unificado** — mesmo que a clínica não use 100% de tudo no dia 1, já tem o que precisa **sem sistemas separados**.

**Implicação de produto/venda:**

- Acompanhamento pós-operatório é **ferramenta dentro** da gestão clínica, não o único “produto principal” no discurso de venda.
- Se vender só como “app de pós-op”, fica difícil comercializar.
- O caminho é **gestão da clínica**, com pós-op como diferencial forte no meio do processo.
- O que é **complexo** (ex.: TISS) costuma ser onde está o problema e onde o cliente paga — mas ainda assim se **constrói na ordem lógica do processo**, sem pular o início.

Site: https://uniosaude.uniowork.com.br  
Branch / deploy: `uniosaude`

---

## Processo completo da clínica (lista Fernando)

Ordem lógica do início ao fim:

| # | Etapa | Unio Saúde hoje | Próximo / depois |
|---|--------|-----------------|------------------|
| 1 | Captação de cliente | — | Depois / integrar |
| 2 | Leads / funil de vendas | — | Depois / integrar (CRM) |
| 3 | Cadastro de clientes | Parcial (paciente clínico) | Expandir cadastro único |
| 4 | Agendas | **MVP+** (`/pos-operatorio/agenda`) | WhatsApp Meta live (quando `WHATSAPP_META_*`) |
| 5 | Atendimentos | **MVP E2** (`/pos-operatorio/atendimento`) | SOAP leve + evolução na ficha |
| 6 | Acompanhamento do histórico do paciente | **Forte (pós-op, eventos, guia)** | Continuar aprofundando |
| 7 | Faturamento de planos | **MVP E3 + E4 fundação** | Contas + convênios + guias; XML ANS depois |
| 8 | Financeiro | — | Depois |
| 9 | Contabilidade | — | Depois / integrar |
| 10 | Cadastros (gerais) | Parcial | Expandir |

**Leitura:** hoje a Unio pega forte o **#6** (e parte do #5). O crescimento natural é **#4 → #5 → #7**. Captação/CRM (#1–2) e financeiro/contábil (#8–9) não são núcleo agora.

---

## Etapas de construção (continuidade + o que vimos agora)

Uma só trilha — pós-op continua como diferencial; gestão completa se constrói em cima.

| Etapa | Nome | Status | O que entrega | Landing / plataforma |
|-------|------|--------|---------------|----------------------|
| **E0** | Continuidade (já) | **Entregue** | Pós-op, alertas, sala crítica, portal, carteirinha, guia, comprovante, ops | Pitch diferencial |
| **E1** | Agenda | **Fechada (MVP+)** | Dia/semana + recepção + WA manual + **confirmação D-1** (comando/cron) | Módulo Agenda na landing + nav |
| **E2** | Atendimento | **MVP+ PEP leve** | SOAP + hipótese/CID; finaliza → `atendido` + evolução | Nav Atendimento |
| **E3** | Faturamento leve | **MVP entregue** (particular) | Conta `aberto` ao finalizar; pago / cortesia / cancelar | Nav Contas |
| **E4** | TISS | **MVP+ anti-glosa** | Convênios + guia + lote + XML + checklist pré-envio + painel glosas | Nav Guias / Anti-glosa |
| **E5** | Captação / caixa | **Parcial** | CRM→paciente; contas a receber (KPIs); WhatsApp Meta live | CRM + Contas |

**Regra:** não pular E1→E4. Confirmação WhatsApp entra no fim de E1. Login editorial e copy de venda já falam **gestão + cuidado depois da alta**.

---

## O que já existe (hoje)

### Paciente / continuidade

| Área | O que entrega |
|------|----------------|
| **Pós-operatório** | Protocolos, questionário diário, alertas P1–P4, plantão, escalação, continuidade |
| **Portal / área do paciente** | Hub + portal pós-op (CPF + código em dois passos) |
| **Carteirinha digital** | Identidade clínica, validação, download |
| **Guia médico** | Orientações por fase |
| **Comprovante** | Documento com QR / verificação pública |

### Clínica / ops (já no ar, pouco citado no pitch curto)

Fila do dia, sala crítica, qualidade/SLA, retornos, **agenda MVP**, biblioteca de protocolos, plantão, configurações, webhook/alta, compliance/retenção, demo sandbox.  
Canais WhatsApp/SMS: **WhatsApp live via Meta Cloud API** quando `WHATSAPP_PROVIDER=meta` + token/phone id no `.env`; senão wa.me + webhook (prepared). Log em `clinic_outbound_message`.

Tudo no **mesmo organismo clínico** (Unio Saúde).

---

## Roadmap de construção (ordem lógica)

Não começar pelo TISS completo. Começar pelo processo:

```text
Histórico / pós-op (já)
  → Agenda (#4)
  → Atendimento (#5)
  → Faturamento leve / planos (#7)
  → TISS (convênio, quando a base estiver sólida)
  → Financeiro / contábil (#8–9) e captação (#1–2) depois ou por integração
```

Combinado com Arthur: **Agenda → faturamento → TISS**.  
Combinado com Fernando: isso são peças da **gestão completa**; pós-op é ferramenta **dentro** desse sistema, não o único chapéu de venda.

### 1. Agendamento

**Status MVP / E1:** lista semanal + visão do dia, status de recepção, **confirmação WhatsApp (Meta live ou wa.me)** + KPIs do dia + **lembrete D-1** (`app:clinic:agenda-reminders` / continuidade). Evento webhook `agenda_confirmacao` + log de outbound.

- Calendário por médico / sala *(sala e overlap avançado: depois)*
- Status: `marcado` → `confirmado` → `faltou` / `cancelado` → `atendido`
- Retorno do protocolo = **sugestão** de horário *(já no MVP)*
- Confirmação WhatsApp depois

### 2. Atendimento (E2 MVP+)

**Status:** tela SOAP (queixa / exame / conduta / observação) + **hipótese diagnóstica e CID-10**; finalizar fecha o horário como `atendido` e grava evolução na ficha.

### 3. Faturamento leve (E3 MVP)

**Status particular:** conta nasce ao **finalizar atendimento** (`aberto`); marcar pago, cortesia ou cancelar em Contas. Sem gateway / NF / TISS nesta leva.

- Particular / cortesia / (depois) convênio
- Status: `aberto` → `pago` | `cancelado` (glosa depois, com TISS)
- Recibo simples no particular *(depois)*

**Regra-mãe:** `marcado` → `atendido` → `faturado` (ou sem cobrança).

### 4. TISS (fundação MVP)

**Status:** cadastro de convênios, conta tipo `convenio`, guia com nº/senha/itens TUSS manuais e status `rascunho → enviado → autorizado → glosado|pago|cancelado`. Sem XML/lote ANS nesta leva.

- Padrão ANS com o convênio (guia, autorização, cobrança, glosa) — **XML/lote: depois**
- Entra depois de agenda + faturamento; sem isso, TISS vira arquivo solto
- Fernando: o complexo costuma ser onde está a dor e a disposição de pagar — desde que a base do processo já exista

---

## Ideias extras (além da lista 1–10)

| Prioridade | Ideia | Nota |
|------------|--------|------|
| Alta | Prontuário leve (SOAP / evolução) | Atendimento (#5) mais completo |
| Alta | Confirmação automática WhatsApp/SMS | Agenda (#4) |
| Média | Fila do dia na recepção | Já há ops; aprofundar |
| Média | Multi-unidade / multi-profissional | Redes (ex.: União Médica) |
| Média | Estoque / kits | Depois |
| Depois | Teleconsulta, NPS | Diferencial |

---

## Princípios de produto (anotações da conversa)

1. **Pensar no processo do início ao fim** (lista Fernando), não em feature isolada.
2. **Um sistema unificado** > vários sistemas “um pra cada coisa”.
3. Clínica pode **não usar tudo** no começo — desde que o que precisa já esteja na mesma plataforma.
4. Pós-op / histórico = **módulo forte**, não o único posicionamento de venda.
5. Complexidade (TISS, financeiro) = oportunidade de valor, mas **na hora certa** da sequência.
6. Começar do TISS = pular o início. Começar de histórico + agenda = onde a dor aparece cedo.

---

## Pitch (ajustar conforme o interlocutor)

**Curto (dor pós-op):**  
Paciente operou, foi pra casa, e a clínica não acompanha — Unio fecha esse buraco.

**Venda / gestão (Fernando):**  
Clínicas vivem com um sistema pra cada coisa. Unio Saúde caminha para gestão unificada: cadastro e histórico do paciente, pós-op, e em seguida agenda, atendimento e faturamento — sem trocar de ferramenta a cada etapa.

**Uma frase:**  
*Unio Saúde: da continuidade do paciente à gestão da clínica — um sistema só, módulo a módulo, na ordem do processo.*

---

## Contexto União Médica

- Já houve desenvolvimento de **portais e sistemas** para a União Médica.
- Fernando testou o ambiente (login staff; preferiu desktop ao celular).
- Feedback estratégico: gestão completa, processo 1–10, não vender só pós-op, unificar sistemas, respeitar ordem lógica, TISS como complexidade que vale pagar — no tempo certo.
- Próximo alinhamento útil: **o que mais dói na União nessa lista (1–10)** e por onde entrar de verdade.

---

## Pesquisa de mercado — outras plataformas (jul/2026)

Referências públicas: **Feegow**, **iClinic**, **Clinicorp**, **Operatório**, **Triagefy**, e (hospitalar) MV/Tasy — estes últimos são outro jogo (HIS), não o mesmo ICP.

### O que o mercado “completo” costuma ter

| Módulo | Feegow / iClinic / similares | Unio hoje | Gap |
|--------|------------------------------|-----------|-----|
| Agenda multi-profissional | Sim | MVP parcial (`/pos-operatorio/agenda`) | Evoluir visão dia + status recepção |
| Confirmação / anti no-show (WhatsApp) | Sim | **Live (Meta)** ou wa.me | Configurar `WHATSAPP_META_*` |
| Prontuário eletrônico (PEP) | Sim (às vezes IA) | Não (só evolução pós-op / guia) | Alto |
| Faturamento particular | Sim | Não | Alto |
| Convênio + **TISS** / TUSS / glosa | Sim (Feegow forte) | Sim (guia, lote, XML, catálogo TUSS) | Evoluir XSD/operadora |
| Financeiro (caixa, DRE, repasse) | Sim | Não | Médio–alto |
| Estoque / materiais | Sim | Não | Médio |
| Teleconsulta | Sim | Não | Depois |
| CRM / leads / marketing | Sim (iClinic, Triagefy, Clinicorp) | Não | Médio (Fernando #1–2) |
| Captação + funil até cirurgia | Triagefy / Operatório | Parcial (pós-op) | Médio |
| Pós-op com protocolo, alerta, plantão, SLA | Fraco ou CRM genérico | **Forte** | Diferencial Unio |
| Carteirinha / comprovante / guia paciente | Raro como produto | **Forte** | Diferencial Unio |
| Ops clínica (sala crítica, qualidade) | Parcial | **Forte** | Diferencial Unio |
| Certificação SBIS / PEP certificado | Feegow destaca | Não | Depois (compliance) |
| API / integrações lab / imagem | Comum | Webhook / alta | Médio |

### Leitura competitiva

- **Gestão “ERP clínico”** (Feegow, iClinic): vendem agenda + prontuário + financeiro + TISS. Pós-op contínuo com triagem/alerta é raso.
- **CRM cirúrgico / estética** (Triagefy, Clinicorp, Operatório): fortes em lead → agenda → pré/pós comercial; fracos em sala crítica / SLA clínico / documentos oficiais do paciente.
- **Unio hoje:** forte em **continuidade clínica + documentos do paciente + ops de risco**; fraca no que o mercado chama de “sistema completo” (agenda, PEP, caixa, TISS).

### O que falta para a Unio ser “plataforma completa” (priorizado)

| Ordem | Adicionar | Por quê |
|-------|-----------|---------|
| 1 | **Agenda** | Base de qualquer gestão; sem isso não vende como sistema completo |
| 2 | **Atendimento / prontuário leve** | Fecha consulta e histórico além do pós-op |
| 3 | **Faturamento particular** | Onde o cliente sente dinheiro cedo |
| 4 | **Confirmação WhatsApp + fila do dia** | Mercado já espera; reduz falta |
| 5 | **Cadastro unificado** (beneficiário / paciente / convênio) | Lista Fernando #3 e #10 |
| 6 | **TISS / convênios** | Complexo e pagável; depois da fatura |
| 7 | **Financeiro** (caixa, repasse) | Fecha gestão |
| 8 | **CRM / captação** | Ou integrar; não precisa reinventar no dia 1 |
| 9 | Estoque, teleconsulta, contábil, SBIS | Escala / compliance |

Não copiar Feegow feature a feature. Completar a **coluna vertebral** (agenda → atendimento → fatura → TISS) e manter o **diferencial** (pós-op + alertas + documentos do paciente) como motivo de escolha.

### Leitura OnDoctor (jul/2026)

Referência: [ondoctor.app](https://www.ondoctor.app/) — ERP clínico com agenda, PEP, tele, financeiro, WhatsApp e pitch “um sistema no lugar de vários”.

| Ideia OnDoctor | Como Unio supera depois |
|----------------|-------------------------|
| Agenda com status coloridos (chegou / em atendimento) | Evoluir MVP agenda: visão dia + status de recepção ligados à fila/check-in |
| Lembrete WhatsApp 1 dia antes | Confirmação no fluxo pós-op + agenda (Meta Cloud API + cron `app:clinic:agenda-reminders`) |
| “Um sistema no lugar de vários” | Pitch Unio: gestão + **cuidado depois da alta** (diferencial que eles não têm) |
| PEP / tele / financeiro / Power BI | Manter ordem atual: agenda → atendimento → fatura → TISS |

Login Unio Saúde: painel editorial com slides de valor próprio (continuidade, sala crítica, agenda do protocolo, documentos) — mesma *ideia* de split + promessa, sem clonar visual/copy.

---

## Para qual setor a Unio serve

### ICP principal (encaixa hoje + roadmap)

| Setor | Por quê |
|-------|---------|
| **Clínicas cirúrgicas ambulatoriais** | Pós-op é dor real; retorno e protocolo fazem sentido |
| **Cirurgia plástica / estética** | Alto ticket, WhatsApp solto, retorno crítico, documento do paciente |
| **Ortopedia / trauma ambulatorial** | Protocolos D+n, curativo, retorno |
| **Bariátrica / jornadas longas** | Acompanhamento meses/anos; mercado já compra CRM+pós-op |
| **Clínicas / redes multi-profissional** (ex.: União Médica) | Um sistema unificado; white-label e multi-unidade no caminho |
| **Day clinic / centro cirúrgico ambulatorial** | Alta → casa → alerta; carteirinha/comprovante |

### ICP secundário (depois de agenda + fatura)

Consultórios especializados com convênio, clínicas que hoje usam Feegow/iClinic mas sofrem no pós-op (Unio como módulo/complemento ou migração parcial).

### Fora do foco (não competir agora)

| Setor | Motivo |
|-------|--------|
| Hospital geral / HIS (MV, Tasy) | Outro porte, outra regulação, outro ciclo de venda |
| Só odontologia pura | Clinicorp etc. já dominam; só se houver braço cirúrgico |
| Laboratório / diagnóstico como núcleo | Integração depois, não produto core |
| Convênio/operadora de saúde | Cliente é a **clínica**, não a operadora |

### Posicionamento de setor (uma linha)

**Unio Saúde = gestão clínica para quem opera e precisa cuidar do paciente depois da alta** — começando pela continuidade e caminhando para agenda, atendimento e faturamento no mesmo sistema.

---

## Planos comerciais (jul/2026)

Catálogo em código: `src/PosOperatorio/ClinicCommercialPlans.php` · landing `#planos` · Comercial → Limites.

| Plano | Preço | Pacientes | Escopo |
|-------|-------|-----------|--------|
| **Essencial** | R$ 189 / clínica / mês | ≤ 100 | Continuidade: pacientes, protocolos, alertas, portal |
| **Clínica** (recomendado) | R$ 279 / clínica / mês | ≤ 500 | Stack atual + agenda (dia/semana, status, WA manual) + docs |
| **Rede** | Sob consulta (~a partir de R$ 499) | ≤ 2000 / cotação | White-label, multi-unidade, prioridade no roadmap |

**Comparativo de mercado (referência interna jul/2026):** ver [UNIOSAUDE_PRECOS_MERCADO.md](UNIOSAUDE_PRECOS_MERCADO.md).  
OnDoctor ~R$ 79,90/usuário · Feegow ~R$ 129+/profissional · Clinicorp ~R$ 127/clínica. Unio **não compete no piso de R$ 79** — cobra por clínica com o diferencial de continuidade pós-alta. **Não publicar esse comparativo na landing.**

**Especialidades na landing (ICP, não produto por especialidade ainda):** plástica/estética, ortopedia, bariátrica, day clinic, rede multidisciplinar, outras cirúrgicas.

IDs legados no Comercial: `profissional` → `clinica`, `premium` → `rede`.

---

## Referências internas

- [UNIOSAUDE_TISS_XML.md](UNIOSAUDE_TISS_XML.md)
- [UNIOSAUDE_PRECOS_MERCADO.md](UNIOSAUDE_PRECOS_MERCADO.md)
- [OPERACAO_INDICE.md](OPERACAO_INDICE.md)
- [UNIOSAUDE_ACESSOS.md](UNIOSAUDE_ACESSOS.md)
- [HUB_POS_OPERATORIO.md](HUB_POS_OPERATORIO.md)
- [UNIOSAUDE_BANCO.md](UNIOSAUDE_BANCO.md)

### Fontes de mercado (consulta)

- Feegow Clinic — feegowclinic.com.br  
- iClinic — iclinic.com.br  
- Clinicorp / Triagefy / Operatório — posicionamento CRM e cirúrgico  
- Comparativos públicos Feegow × Clinicorp (gestão 2025/2026)  
