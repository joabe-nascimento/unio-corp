# Checklist INPI — Registro do Unio (RPC)

Guia prático para registrar o **programa de computador** da plataforma Unio no INPI, usando o CNPJ do titular.

> **Aviso:** orientação operacional, não substitui advogado de PI ou contador. Valores e regras podem mudar — confira sempre no [site oficial do INPI](https://www.gov.br/inpi/pt-br/servicos/programas-de-computador).

---

## Titular sugerido (consultado em jul/2026)

| Campo | Valor |
|-------|--------|
| **CNPJ** | 58.650.699/0001-15 |
| **Razão social** | 58.650.699 JOABE FONSECA DO NASCIMENTO |
| **Natureza** | Empresário Individual (EI) — **ativa** |
| **MEI** | Não (excluído do MEI em 31/12/2025) |
| **CNAE principal** | 8599603 — Treinamento em informática |
| **Município** | Feira de Santana — BA |

**Titular no INPI:** pessoa jurídica (CNPJ acima).  
**Autor(es):** você (Joabe) e, se houver, outros desenvolvedores que contribuíram de forma relevante.

---

## O que o registro RPC faz (e o que não faz)

| Faz | Não faz |
|-----|---------|
| Comprova **titularidade e data** do software | Não é **patente** de invenção |
| Certificado em ~7 dias úteis (processo automatizado) | Não impede cópia só com o registro |
| Validade **50 anos** (taxa única) | **Gov.br não serve** para assinar — exige **e-CNPJ** ICP-Brasil |

---

## Antes de começar — requisitos

- [ ] **Certificado digital e-CNPJ** (ICP-Brasil, certificado **qualificado**) — titular PJ  
  - **Não aceita:** Gov.br, assinatura avançada, ACOAB  
- [ ] **Cadastro no e-INPI** (login/senha no portal)  
- [ ] **GRU paga** — código de serviço **730** (Registro de Programa de Computador)  
  - Valor de referência: **R$ 185,00** (confirmar no momento do pagamento)  
- [ ] **Código-fonte organizado** para gerar o hash (ver abaixo)  
- [ ] **Backup seguro** do arquivo/ZIP usado no hash (guardar para sempre)

---

## Passo a passo INPI (RPC)

### 1. Cadastro e pagamento

1. Acesse https://www.gov.br/inpi/pt-br/servicos/programas-de-computador  
2. Crie/acessse conta **e-INPI**  
3. Emita e pague a **GRU código 730**  
4. Guarde número da GRU paga

### 2. Preparar o “depósito” (hash — não envia código ao INPI)

O INPI **não recebe** o código-fonte completo. Você gera um **resumo hash** (SHA-256 ou SHA-512) de:

- **Um arquivo** (PDF, TXT, DOC…) **ou**
- **Um ZIP/RAR** com a documentação técnica / trechos do código

**Regra de ouro:** o arquivo usado no hash deve ficar **intocado** em backup (nuvem + HD). Se precisar provar autoria na Justiça, esse arquivo + o hash do certificado INPI devem bater.

#### Sugestão para o Unio

Monte um pacote representativo da **versão que está registrando**, por exemplo:

```
unio-rpc-v1.0/
├── DESCRICAO_TECNICA.pdf ou .txt   ← linguagem, arquitetura, módulos
├── LISTAGEM_MODULOS.txt            ← RH, Core, deploy, etc.
└── amostras_codigo/                ← trechos ou export seletivo (opcional)
```

Ou compacte uma **tag Git** específica (ex.: commit de `production` em jul/2026):

```powershell
# Exemplo no Windows — ajuste caminhos
cd C:\projetos\huplex
git archive --format=zip --output=unio-rpc-v1.0-src.zip production

# Hash SHA-256 (PowerShell)
Get-FileHash -Path unio-rpc-v1.0-src.zip -Algorithm SHA256
```

Anote:

- [ ] Algoritmo (ex.: **SHA-256**)  
- [ ] Hash completo (hexadecimal)  
- [ ] Cópia do ZIP em local seguro (backup)

> **Exclua do ZIP:** `.env`, `.env.local`, senhas, `vendor/` se o arquivo ficar gigante (o INPI aceita documentação técnica + amostras; consulte o [Guia Básico RPC](https://www.gov.br/inpi/pt-br/servicos/programas-de-computador/guia-basico)).

### 3. Declaração de Veracidade (DV)

1. Baixe a **DV** no fluxo da GRU / e-Software (PDF gerado pelo sistema)  
2. Assine com **e-CNPJ** (titular)  
3. **Não reimprima** nem regenere o PDF após assinar

### 4. Formulário e-Software

Preencha no [e-Software](https://www.gov.br/inpi/pt-br/servicos/programas-de-computador):

| Campo | Sugestão Unio |
|-------|----------------|
| **Título do programa** | Unio — Plataforma de Gestão de Pessoas |
| **Versão** | 1.0 (ou a que corresponde ao ZIP/hash) |
| **Titular** | CNPJ 58.650.699/0001-15 |
| **Autor(es)** | Joabe Fonseca do Nascimento (+ coautores se houver) |
| **Data de criação** | Data da primeira versão estável (ex.: 2025 ou 2026) |
| **Linguagem** | PHP 8.2, Symfony 7.4, JavaScript, SQL |
| **Campo operacional** | Gestão de RH, operações, multi-empresa (SaaS) |
| **Hash + algoritmo** | Valores gerados no passo 2 |
| **Anexo** | DV assinada digitalmente |

### 5. Depois do protocolo

- [ ] Acompanhar processo no **Busca Web** INPI  
- [ ] Baixar **certificado eletrônico** quando deferido  
- [ ] Arquivar: certificado + ZIP/hash + GRU + DV

---

## Checklist do que separar antes (pasta `inpi-rpc/`)

### Documentos administrativos

- [ ] Cartão CNPJ (comprovante situação **ativa**)  
- [ ] Contrato social / CCMEI / certidão EI (se pedirem em outro contexto)  
- [ ] CPF do responsável legal (você)  
- [ ] Comprovante GRU 730 paga  

### Sobre o software Unio

- [ ] **Nome comercial:** Unio  
- [ ] **Site:** https://uniowork.com.br  
- [ ] **Repositório:** `joabe-nascimento/unio-corp` (referência interna — não vai no INPI)  
- [ ] **Commit/tag registrada:** anotar hash Git + data (ex.: `production` @ jul/2026)  
- [ ] **Descrição técnica** (1–3 páginas): stack, módulos (RH, Core, TI…), arquitetura multi-empresa, deploy  
- [ ] **Prints** da interface (login, dashboard, RH) — podem ir no PDF de descrição  
- [ ] **ZIP para hash** + backup em 2 locais  
- [ ] **Hash SHA-256** anotado  

### Certificado digital

- [ ] **e-CNPJ** válido no CNPJ 58.650.699/0001-15  
- [ ] Testar assinatura em PDF antes (DV)  

### Autoria e titularidade

- [ ] Listar **autores** (quem escreveu código relevante)  
- [ ] Se só você desenvolveu: titular = CNPJ, autor = você (PF)  
- [ ] Se professor/coautores contribuíram: alinhar **cessão** ou coautoria **antes** do registro  

---

## Marca “Unio” (opcional, processo separado)

Registro de **marca** ≠ registro de **programa**.

| Item | Marca |
|------|--------|
| O que protege | Nome, logo, identidade visual |
| Onde | INPI → Marcas |
| Pesquisa prévia | Verificar se “Unio” / “Uniowork” já existem na mesma classe (software/SaaS — classe 42) |
| Titular | Mesmo CNPJ ou PF |

Faça **depois** ou **em paralelo** ao RPC, com orientação se achar marca similar.

---

## CNAE × atividade (contador)

Seu CNAE atual é **treinamento em informática**. O Unio é **desenvolvimento/licenciamento de software SaaS**.

- [ ] Conversar com contador sobre **CNAE secundário** (ex.: desenvolvimento de programas, licenciamento)  
- [ ] Isso **não bloqueia** o RPC no INPI, mas ajuda em nota fiscal e consistência cadastral  

---

## Ordem sugerida (cronograma)

| # | Ação | Tempo estimado |
|---|------|----------------|
| 1 | Obter/renovar **e-CNPJ** | 1–5 dias |
| 2 | Montar ZIP + descrição técnica + hash | 2–4 h |
| 3 | Cadastro e-INPI + GRU 730 | 1 h |
| 4 | Assinar DV + preencher e-Software | 1–2 h |
| 5 | Certificado RPC | até ~7 dias úteis |
| 6 | (Opcional) Pedido de marca | semanas/meses |

---

## Links oficiais

| Recurso | URL |
|---------|-----|
| Serviço RPC | https://www.gov.br/pt-br/servicos/solicitar-o-registro-de-programa-de-computador |
| Guia básico | https://www.gov.br/inpi/pt-br/servicos/programas-de-computador/guia-basico |
| FAQ programas | https://www.gov.br/inpi/pt-br/acesso-a-informacao/perguntas-frequentes/programas-de-computador |
| e-INPI | https://gru.inpi.gov.br/pePI/ |

---

## Resumo em uma frase

**CNPJ ativo → e-CNPJ → ZIP do Unio → hash SHA-256 → GRU 730 → DV assinada → e-Software → certificado RPC.**

---

*Checklist criado em 4 de julho de 2026 — titular: 58.650.699/0001-15 (EI ativa).*
