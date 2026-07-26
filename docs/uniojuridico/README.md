# Unio Jurídico

Plataforma de gestão para escritórios de advocacia, construída sobre o mesmo shell
**Organismo** do Unio Saúde (branch `uniosaude`), com identidade visual própria e uma
IA jurídica dedicada (**Bruna**, via **JurisFlow AI Service**) plugada no chat
Vitória/Lumen já existente.

> Branch: `uniojuridico` (criada a partir de `uniosaude`).
> Escopo desta branch: **identidade** (marca, cores, vocabulário) + **integração de IA**.
> Os módulos funcionais (Pulso, Colônia, Membros, Cena) são os mesmos do shell genérico —
> nenhuma feature clínica (pós-operatório, pacientes, protocolos) é exibida, pois o app
> só ativa esse conjunto quando o perfil detectado é "clínica" (`org_clinic`).

## 1. Identidade

| Item | Valor |
|---|---|
| Nome da marca | **Unio Jurídico** |
| Slogan | "Justiça que acompanha." |
| Cor primária | `#9C2C3C` (bordô) com acento dourado `#B8892B` |
| Logo | `public/images/logos/unio-juridico.png` |
| Favicon/mark | `public/images/logos/favicon-unio-juridico.png` |
| Tema CSS | `public/css/unio-juridico-theme.css` (sobrescreve `--org-blue*` do shell Organismo) |
| Assistente de IA | **Bruna** — assistente jurídica |

A detecção de perfil é automática: `OrganismoCopyService::isJuridicoProfile()` retorna
`true` quando `UNIO_ORGANISMO_BRAND_NAME` (ou `UNIO_ORGANISMO_UNIT_LABEL`) contém
"jurídico", "juridico", "advocacia" ou "escritório". Isso ativa:

- a classe `org-juridico` no `<body>` (tema visual);
- o global Twig `org_juridico` (usado em `helix_assistant.html.twig` e demais templates);
- o logo/favicon padrão da Unio Jurídico (`PlatformConfigExtension`);
- o roteamento do chat para o backend JurisFlow (`VitoriaApiController::activeClient()`).

Todo o vocabulário de navegação (Clientes, Prazos, Modelos de Petição, Portal do
Cliente etc.) é configurado via variáveis de ambiente — veja `.env.uniojuridico.example`
na raiz do projeto — exatamente como o Unio Saúde faz para "Pacientes", "Protocolos" etc.

## 2. IA Jurídica — JurisFlow AI Service

O motor de IA já existe e é multi-vertical, pensado para ser reutilizado em outros
nichos (`C:\projetos\projeto-unef\Nova pasta\JurisFlow-ai-service`, vertical `legal`
em `app/verticals/legal/`). Para este projeto reaproveitamos o serviço **como está**,
apenas conectando o Symfony a ele:

```
Symfony (Unio Jurídico)  ──HTTP──▶  JurisFlow AI Service (FastAPI/LangChain)
  VitoriaApiController                app/main.py
  └─ JurisFlowAiClient                 POST /v1/assistant/bruna/chat
     src/Service/Juridico/             GET  /health
```

### 2.1 Subir o serviço de IA localmente

```bash
cd "C:\projetos\projeto-unef\Nova pasta\JurisFlow-ai-service"
.venv\Scripts\activate
uvicorn app.main:app --reload --port 8090
```

Configure `AI_VERTICAL=legal` no `.env` do serviço (já é o padrão).

### 2.2 Conectar o Symfony ao serviço

No `.env.local` da Unio Jurídico:

```bash
LEGAL_AI_ENABLED=true
LEGAL_AI_URL=http://127.0.0.1:8090
LEGAL_AI_ESCRITORIO_ID=default
```

O `escritorio_id` isola a base de conhecimento (RAG) por tenant no JurisFlow. O
`VitoriaApiController` já envia automaticamente o id da empresa ativa do usuário
logado como `escritorio_id` — múltiplos escritórios cadastrados na mesma instalação
ficam com memórias/RAG separados sem configuração adicional.

### 2.3 Como o chat decide o backend

`src/Controller/Api/VitoriaApiController.php`:

```php
private function activeClient(): VitoriaClient|JurisFlowAiClient
{
    if ($this->organismoCopy->isJuridicoProfile()) {
        return $this->juridicoAi;
    }

    return $this->vitoria;
}
```

Ou seja: a **mesma** UI do chat (`templates/components/helix_assistant.html.twig`,
`helix_dock.html.twig`) e a **mesma** rota (`POST /api/vitoria/chat`) continuam
funcionando — só o backend muda, sem exigir nenhuma alteração de front-end para
trocar de vertical no futuro.

### 2.4 Funcionalidades extras já plugadas na Bruna

O orquestrador do JurisFlow (`bruna_orchestrator`) decide sozinho, por mensagem,
se deve responder via RAG (pesquisa na base de conhecimento) ou acionar o **Agente
com Tools** (ReAct) para:

- calcular prazos processuais;
- pesquisar jurisprudência;
- calcular honorários advocatícios (tabela OAB);
- buscar informações no RAG do escritório.

Os atalhos de sugestão no chat (`helix_assistant.html.twig`, bloco `_juridico`) já
apontam perguntas prontas para essas capacidades: "Calcular prazo", "Jurisprudência",
"Resumir documento", "Analisar contrato" e "Calcular honorários".

## 3. Deploy

Segue o mesmo padrão do Unio Saúde (deploy manual via SSH/HostGator, sem CI
automático no push):

- Workflow (manual, `workflow_dispatch`): `.github/workflows/deploy-uniojuridico.yml`
- Script local (Windows): `scripts/deploy-uniojuridico-manual.ps1`
- Config local (não versionada): copie `config/deploy-uniojuridico.local.env.example`
  para `config/deploy-uniojuridico.local.env` e informe `DEPLOY_KEY_FILE`.
- Branch protegida contra reset automático em `config/deploy-branches.txt`.

Os caminhos (`/home2/joabef36/unio-uniojuridico`, domínio
`uniojuridico.uniowork.com.br`) são placeholders seguindo a convenção das demais
instalações Unio — ajuste conforme o provisionamento real do servidor/domínio.

## 4. Checklist de um novo deploy (primeira vez)

1. Provisionar subdomínio + banco de dados no cPanel (mesmo processo do Unio Saúde).
2. Copiar `.env.uniojuridico.example` → `.env.local` no servidor e preencher
   `APP_SECRET`, `DATABASE_URL`, `LEGAL_AI_URL` (URL pública do JurisFlow, se também
   for hospedado).
3. Subir o `JurisFlow AI Service` (Python) em uma VM/serviço próprio e apontar
   `LEGAL_AI_URL` para ele (HTTPS recomendado em produção).
4. Rodar `scripts/deploy-uniojuridico-manual.ps1` a partir da branch `uniojuridico`.
5. Acessar `/admin/configuracoes` para ajustar logo/cores caso quiera sobrescrever
   os padrões (`Unio Jurídico`, bordô/dourado) já pré-configurados no código.

## 5. O que é reaproveitado do Unio Saúde (sem alteração funcional)

- Autenticação, workspaces, multi-empresa;
- Shell Organismo (Colônia, Pulso, Cena, Lumen/Vitória);
- WhatsApp (lembretes — reaproveitável para prazos/audiências);
- Asaas (cobrança — reaproveitável para honorários);
- Toda a infraestrutura de permissões, e-mail, LGPD etc.

Nenhum módulo específico de saúde (pós-operatório, pacientes, protocolos clínicos,
carteirinha) é exibido nesta identidade, pois esses módulos só aparecem quando o
perfil ativo é "clínica" (`org_clinic`).
