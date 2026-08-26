# Unio Jurídico — Arquitetura

> Documento de referência para desenvolvedores e para processos seletivos.
> Última atualização: agosto/2026.

---

## Visão geral

O **Unio Jurídico** é o vertical de escritórios de advocacia da plataforma Unio.
Roda como um produto separado dentro do mesmo monólito Symfony (branch `uniojuridico`),
isolado por prefixos de rota, módulos e um *registry* dedicado.

```
Browser / Mobile
      │
      ▼
┌─────────────────────────────────────────────────┐
│  Symfony 7.4  (PHP 8.2)  — branch uniojuridico  │
│                                                  │
│  Controller/Juridico/*     ← rotas /juridico/    │
│  Service/Juridico/*        ← lógica de domínio   │
│  Contract/LegalAiClientInterface                 │
│  Config/JuridicoModuleRegistry                   │
│  Entity/* (Processo, Cliente, Prazo…)            │
│                                                  │
│  Sasha Tools (ConfirmableTool pattern)           │
│       │                                          │
│       ▼                                          │
│  JurisFlowAiClient (HTTP)  ──────────────────────┼──► JurisFlow AI Service
│  NullLegalAiClient   (stub/testes)               │    (Python FastAPI)
└─────────────────────────────────────────────────┘
      │
      ▼
  MySQL / Doctrine ORM
```

---

## Componentes principais

### Controllers — `src/Controller/Juridico/`

| Classe | Responsabilidade |
|--------|-----------------|
| `ProcessoController` | CRUD de processos (CNJ, partes, movimentações) |
| `ClienteController` | Cadastro de clientes do escritório |
| `PrazoController` | Agenda de prazos com alertas |
| `AudienciaController` | Calendário de audiências |
| `HonorarioController` | Lançamentos e apurações de honorários |
| `TemplateController` | Biblioteca de peças/modelos |
| `IaOpsController` | Painel de operações da IA jurídica |
| `ApiPublicaController` | API pública multi-tenant (tokens por escritório) |

### Services — `src/Service/Juridico/`

| Classe | Responsabilidade |
|--------|-----------------|
| `JurisFlowAiClient` | Implementação HTTP da `LegalAiClientInterface` |
| `NullLegalAiClient` | Stub nulo para testes unitários e fallback |
| `DataJud/DataJudClient` | Integração com a API pública do CNJ (DataJud) |
| `DataJud/DataJudTribunalMap` | Mapa de tribunais CNJ → prefixos de rota |

### Contrato de IA — `src/Contract/LegalAiClientInterface`

Define todos os métodos de IA jurídica sem acoplamento à implementação HTTP.
Consumidores (Controllers, Sasha Tools) dependem desta interface:

```php
public function __construct(
    private readonly LegalAiClientInterface $legalAi,
) {}
```

Isso permite:
- Injetar `NullLegalAiClient` nos testes sem mock de HTTP.
- Trocar o provedor de IA (ex: OpenAI direto) sem alterar nenhum Controller.

### Sasha Tools — `src/Service/Sasha/Tool/Juridico/`

Ferramentas que a assistente Sasha pode executar no contexto jurídico.
Seguem o padrão `ConfirmableToolTrait`: a ação é proposta primeiro e só
executada após confirmação explícita do usuário (sem side effects acidentais).

### Module Registry — `src/Config/JuridicoModuleRegistry`

Centraliza a definição de módulos do produto Jurídico:
- Quais módulos existem e seu estado (beta, ativo, em breve).
- `GRADUATED_ROUTES`: rotas disponíveis para contratantes do plano Jurídico.
- Usado pelo shell Organismo para renderizar o menu e controlar acesso.

---

## Fluxo de IA (chat jurídico)

```
Usuário envia mensagem
        │
        ▼
 SashaController (ou JuridicoController)
        │  injeta LegalAiClientInterface
        ▼
 JurisFlowAiClient.chat()
        │  POST /v1/assistant/Sasha/chat
        ▼
 JurisFlow AI Service  (FastAPI + LangChain)
        │  RAG → busca chunks relevantes no SQLite/Pinecone
        │  LLM → gera resposta contextualizada
        ▼
 { answer, suggested_actions }
        │
        ▼
 Symfony formata e devolve ao front-end (SSE / JSON)
```

### Retry automático

Se a primeira chamada falhar por `connection refused / timed out`, o cliente
aguarda 1,8 s e tenta uma segunda vez antes de retornar `null`.
Em ambos os casos, o `nudgeWatchdog()` dispara o script de restart do
JurisFlow na HostGator sem bloquear o request principal.

---

## Fluxo de RAG (indexação de peças)

```
TemplateController.create() / ProcessoController.addPeca()
        │
        ▼
 JurisFlowAiClient.indexarDocumentoRag()
        │  POST /v1/rag/{escritorio_id}/documents
        │  best-effort: falha silenciosa (apenas log info)
        ▼
 JurisFlow AI Service → SQLite (dev) / Pinecone (prod)
        │
        ▼
 Disponível para busca semântica via buscarNaRag()
```

---

## Integração DataJud (CNJ)

```
ProcessoController.buscarDataJud()
        │
        ▼
 DataJudClient → GET https://api-publica.datajud.cnj.jus.br/...
        │  autenticação por API Key (env: DATAJUD_API_KEY)
        ▼
 Retorna movimentações em formato CNJ normalizado
        │
        ▼
 Symfony salva/atualiza movimentações do processo
```

---

## Decisões de arquitetura (ADRs)

### ADR-001 · Monólito com vertical isolado vs. microserviço separado

**Decisão**: Manter o Jurídico no mesmo monólito Symfony, em branch dedicada.

**Raciocínio**: Evita a complexidade operacional de dois deploys separados num
estágio inicial. A separação é feita por convenção de código (namespace, prefixo
de rota, registry) e não por rede. O JurisFlow (IA) é o único serviço externo.

**Revisão**: Migrar para microserviço quando a carga de processos exigir escala
independente ou quando houver equipe dedicada ao vertical Jurídico.

---

### ADR-002 · Interface `LegalAiClientInterface` antes de qualquer segundo provedor

**Decisão**: Criar a interface mesmo que a única implementação seja `JurisFlowAiClient`.

**Raciocínio**: Sem a interface, todos os Controllers ficam acoplados à classe
concreta e a troca de provedor (ou os testes) exigem alterações em cascata.
O custo de criar a interface é mínimo; o benefício de desacoplamento é imediato.

---

### ADR-003 · Confirmação explícita nas Sasha Tools

**Decisão**: Nenhuma Sasha Tool modifica dados sem confirmação do usuário
(padrão `ConfirmableToolTrait`).

**Raciocínio**: Em ambiente jurídico, side effects acidentais (criar prazo,
alterar processo) podem ter consequências graves. A confirmação é obrigatória
por design, não por configuração.

---

### ADR-004 · NullLegalAiClient como stub canônico

**Decisão**: Usar `NullLegalAiClient` (null-object pattern) nos testes em vez
de Mocks de PHPUnit para `LegalAiClientInterface`.

**Raciocínio**: Mocks acoplam testes à assinatura interna do método; o
null-object implementa a interface real e permite testes de integração parcial
sem necessidade de configuração extra.

---

## Variáveis de ambiente relevantes

| Variável | Descrição | Padrão |
|----------|-----------|--------|
| `JURISFLOW_ENABLED` | Liga/desliga o JurisFlow | `false` |
| `JURISFLOW_BASE_URL` | URL base do serviço Python | `http://127.0.0.1:8090` |
| `JURISFLOW_ESCRITORIO_ID` | Tenant padrão | `default` |
| `DATAJUD_API_KEY` | Chave da API pública do CNJ | — |

---

## Estrutura de diretórios

```
src/
  Config/
    JuridicoModuleRegistry.php   # módulos, rotas, estado
  Contract/
    LegalAiClientInterface.php   # contrato de IA jurídica
  Controller/
    Juridico/                    # todos os controllers do vertical
  Service/
    Juridico/
      JurisFlowAiClient.php      # implementação HTTP (LangChain)
      NullLegalAiClient.php      # stub para testes
      DataJud/
        DataJudClient.php
        DataJudTribunalMap.php
        DataJudException.php
    Sasha/
      Tool/
        Juridico/                # ferramentas Sasha do vertical
  Entity/                        # entidades Doctrine
tests/
  Service/
    Juridico/
      JurisFlowAiClientTest.php  # testes com MockHttpClient
      NullLegalAiClientTest.php  # testa o contrato e o stub
docs/
  uniojuridico/
    README.md                    # visão de produto e deploy
    ARCHITECTURE.md              # este documento
```
