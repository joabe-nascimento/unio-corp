# Unio Saúde — operação de deploy (jul/2026)

Guia único do fluxo **atual**: o que fazer no dia a dia, por que aparece **X vermelho** no GitHub, e como publicar na HostGator **sem depender do Actions**.

---

## Resumo em 30 segundos

| Ação | Atualiza o site? |
|------|------------------|
| `git push origin uniosaude` | **Não** — só salva no GitHub |
| `deploy-uniosaude-manual.ps1` | **Sim** — publica em produção |
| GitHub Actions no push | **Desativado** (billing bloqueado) |
| GitHub Actions manual (*Run workflow*) | Sim, se billing voltar |

**URL:** https://uniosaude.uniowork.com.br  
**Branch:** `uniosaude`  
**Servidor:** `/home2/joabef36/unio-uniosaude`

---

## Fluxo oficial (use sempre)

```powershell
cd "C:\projetos\Nova pasta\unio-corp\unio-corp"

# 1) Versionar no GitHub
git push origin uniosaude

# 2) Publicar na HostGator
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1
```

Opcional antes do push:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\validate-before-push.ps1
```

Se o build local já foi feito e você só quer reenviar o pacote:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1 -SkipBuild
```

---

## O X vermelho no commit — o que significa?

### Sintoma

No GitHub, commits em `uniosaude` aparecem com **ícone laranja/vermelho (X)** ao lado, workflow **Deploy Unio Saúde** em *failure*.

### Causa (jul/2026)

**Não é bug no código.** O job `validate` falha em **~3–5 segundos** sem executar nenhum step — típico de **billing ou limite do GitHub Actions** esgotado.

Como confirmar:

```powershell
gh run list --branch uniosaude --limit 3
gh api repos/joabe-nascimento/unio-corp/commits/SEU_SHA/check-runs --jq ".check_runs[] | {name, conclusion}"
```

| Check | Significado |
|-------|-------------|
| `validate / validate` → failure em poucos segundos | Actions bloqueado |
| `deploy` → skipped | Nem tentou publicar |
| GitGuardian → pass | Segurança OK — irrelevante para deploy |

### O site quebrou por causa do X?

**Não necessariamente.** O X só indica que o **pipeline automático não rodou**. Produção depende do **deploy manual** (ou de um Actions bem-sucedido no passado).

Verificação rápida:

```powershell
(Invoke-WebRequest -Uri "https://uniosaude.uniowork.com.br/login" -UseBasicParsing).StatusCode
# esperado: 200
```

### O que foi feito para parar o alarme

A partir do commit `5745149`, o workflow `.github/workflows/deploy-uniosaude.yml` **não dispara mais no push** — apenas em **workflow_dispatch** (disparo manual no GitHub).

Assim, novos `git push` em `uniosaude` **não geram X vermelho** por falha de billing.

---

## GitHub Actions vs deploy manual

| | Actions (quando billing OK) | Manual (ativo hoje) |
|--|----------------------------|---------------------|
| **Disparo** | push ou *Run workflow* | você executa o `.ps1` |
| **Requisitos** | secrets no repo + minutos Actions | chave SSH no PC |
| **Build** | runner Ubuntu | seu PC (composer + npm) |
| **Envio** | tar + scp via CI | tar + scp via script |
| **Pós-deploy** | `deploy-server.sh` no servidor | mesmo script |
| **Atualiza produção?** | Sim | Sim |

**Regra prática:** trate o **push** como backup/versionamento e o **`.ps1`** como publicação.

Documentação detalhada do script: [DEPLOY_MANUAL_UNIOSAUDE.md](DEPLOY_MANUAL_UNIOSAUDE.md)

---

## Setup no PC (já configurado em jul/2026)

### Chave SSH

```
C:\Users\joabe\.ssh\unio_deploy
```

Teste:

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br "echo ok"
```

Use **`br1136.hostgator.com.br`** — nunca o domínio público (Cloudflare).

### Config local (não commitada)

Arquivo: `config/deploy-uniosaude.local.env` (modelo: `config/deploy-uniosaude.local.env.example`)

```dotenv
DEPLOY_KEY_FILE=C:\Users\joabe\.ssh\unio_deploy
DEPLOY_PATH=/home2/joabef36/unio-uniosaude
DEPLOY_PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
DEFAULT_URI=https://uniosaude.uniowork.com.br
```

---

## O que o deploy manual faz (por dentro)

1. `composer install --no-dev`
2. `npm ci` + `npm run vendor:sync` + `php bin/minify-css.php`
3. Cria `deploy-uniosaude.tar.gz` (mesmos excludes do Actions)
4. `scp` → `/tmp/` no servidor
5. `ci-remote-extract.sh` → extrai em `unio-uniosaude`
6. `deploy-server.sh` → backup DB, migrations, cache, symlinks `public_html`
7. Smoke em `/login`

Banco (migrations, backup, restore): [UNIOSAUDE_BANCO.md](UNIOSAUDE_BANCO.md)

Relatório local: `deploy-reports/deploy-report.txt`  
Relatório no servidor: `/home2/joabef36/unio-uniosaude/var/log/deploy-report.txt`

---

## Problemas conhecidos e correções

### 1. `scp: stat local "2222"` (Windows)

**Causa:** no Windows, `scp` usa **`-P`** (maiúsculo) para porta; `ssh` usa **`-p`**.

**Status:** corrigido em `scripts/deploy-uniosaude-manual.ps1` (commit `d3bfc95`).

### 2. `DATABASE_URL` não encontrado no primeiro extract

**Causa:** arquivo `deploy-remote.env` gerado com **CRLF** (Windows); variável `DEPLOY_PATH` chegava com `\r` no servidor.

**Status:** corrigido — env remoto agora é gravado com LF apenas.

**Se acontecer de novo:** rode no servidor:

```bash
export DEPLOY_PATH=/home2/joabef36/unio-uniosaude
export PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
bash /home2/joabef36/unio-uniosaude/scripts/deploy-server.sh
```

### 3. HTTPS 404 após deploy

Reparo no Terminal do cPanel — [UNIOSAUDE_DEPLOY_REPAIR.md](UNIOSAUDE_DEPLOY_REPAIR.md)

### 4. Caminho do projeto com espaço

O repositório Git fica em:

```
C:\projetos\Nova pasta\unio-corp\unio-corp
```

Sempre use aspas no `cd`:

```powershell
cd "C:\projetos\Nova pasta\unio-corp\unio-corp"
```

A pasta pai `unio-corp` **não** é o repositório Git.

### 5. Aviso `PROCESS privilege` no mysqldump

**Sintoma:** no log do deploy aparece `Access denied ... PROCESS privilege ... tablespaces`, mas o backup conclui.

**Causa:** contas HostGator sem privilégio `PROCESS` para exportar tablespaces.

**Status:** corrigido em `scripts/lib/backup-database.sh` com `--no-tablespaces` (quando suportado pelo cliente).

### 6. Log estranho em `migrate-legacy-branding`

**Sintoma:** linha cortada no log ou aviso `ea_php_cli.pm` com newline.

**Causa:** script PHP inline (`php -r`) no cPanel; caracteres especiais no echo.

**Status:** corrigido — lógica em `scripts/lib/migrate-legacy-branding.php`. Log esperado:

```text
migrate-legacy-branding: atualizado plataforma_tagline -> Saúde que acompanha.
```

---

## Validação pós-deploy (produção)

Base: https://uniosaude.uniowork.com.br

| Check | URL / ação |
|-------|------------|
| Login staff | `/login` → HTTP 200 |
| Hub paciente | `/paciente` |
| Carteirinha | `/carteirinha-digital` |
| Comprovante | `/comprovante-procedimento` |
| QR público | `/verificar/{codigo}` (código emitido na clínica) |
| Guia médico | `/guia-medico` |
| Migrations | SSH: `php bin/console doctrine:migrations:status` → `New: 0` |

Demo carteirinha: [UNIOSAUDE_ACESSOS.md](UNIOSAUDE_ACESSOS.md) (CPF + códigos em dois passos).

---

## Reativar deploy automático no push (futuro)

Quando o billing do GitHub Actions estiver regularizado:

1. GitHub → **Settings → Billing** — confirmar minutos disponíveis
2. Testar: **Actions → Deploy Unio Saúde → Run workflow**
3. Se passar, reativar o trigger no workflow:

```yaml
# .github/workflows/deploy-uniosaude.yml
on:
  push:
    branches:
      - uniosaude
  workflow_dispatch:
```

4. Atualizar este doc e [OPERACAO_INDICE.md](OPERACAO_INDICE.md)

---

## WhatsApp Meta + cron (Unio Saúde)

Envio automático de confirmação D-1 e questionário exige no `.env.local` do servidor:

```env
WHATSAPP_PROVIDER=meta
WHATSAPP_META_TOKEN=...
WHATSAPP_META_PHONE_NUMBER_ID=...
WHATSAPP_META_GRAPH_VERSION=v21.0
WHATSAPP_META_VERIFY_TOKEN=...
WHATSAPP_META_APP_SECRET=...
# Single-tenant:
WHATSAPP_META_EMPRESA_CNPJ=00000000000000
# Multi-tenant (substitui a linha acima):
WHATSAPP_META_TENANT_MAP={"PHONE_NUMBER_ID":"CNPJ_SEM_PONTUACAO"}
# Templates HSM aprovados na Meta (fora da janela 24h). Sem nome → envia texto.
WHATSAPP_META_TEMPLATE_AGENDA=confirmacao_agenda
WHATSAPP_META_TEMPLATE_QUESTIONARIO=questionario_pendente
WHATSAPP_META_TEMPLATE_LANG=pt_BR
```

Parâmetros esperados no body do template (ordem):

- **Agenda:** nome, título, data/hora, médico, URL assinada de confirmação
- **Questionário:** nome, dia pós, URL do portal  

Se o template falhar, o sistema faz fallback para mensagem de texto (janela 24h / sessão aberta).

Sem credenciais Meta, o sistema permanece em **wa.me + webhook** (canal “Preparado”).

Cron sugerido (cPanel → Cron Jobs), a partir de `~/unio-uniosaude`:

```cron
15 8 * * * cd /home2/joabef36/unio-uniosaude && php bin/console app:clinic:agenda-reminders --env=prod
30 8 * * * cd /home2/joabef36/unio-uniosaude && php bin/console app:pos-operatorio:send-reminders --env=prod
```

Ops: `/pos-operatorio/lembretes` (status + log) e `/pos-operatorio/integracoes` (teste Meta).

---

## Asaas (Pix e link)

Credenciais ficam somente no `.env.local`; não são gravadas no banco nem no store da clínica:

```env
# Single-tenant:
ASAAS_API_KEY=$aact_...
# Multi-tenant (CNPJ sem pontuação → chave):
ASAAS_API_KEYS_JSON={"00000000000000":"$aact_..."}
# Obrigatório para aceitar webhooks:
ASAAS_WEBHOOK_TOKEN=gere-um-segredo-forte
```

Cadastre `https://uniosaude.uniowork.com.br/api/asaas/webhook` no Asaas e configure
o segredo no header `asaas-access-token`. Comece em sandbox; ative produção somente
depois de validar geração da cobrança e baixa automática da conta.

---

## Histórico (jul/2026)

| Data | Evento |
|------|--------|
| 11/07 | Billing Actions bloqueado — jobs falham em ~4s sem steps |
| 11/07 | Criado `deploy-uniosaude-manual.ps1` + `DEPLOY_MANUAL_UNIOSAUDE.md` |
| 11/07 | Primeiro deploy manual bem-sucedido; `/login` → HTTP 200 |
| 11/07 | Corrigido `scp -P` e line endings no script Windows |
| 11/07 | Desativado deploy Actions no **push** (commit `5745149`) |
| 11/07 | Backup mysqldump: `--no-tablespaces`; branding legado em `.php` dedicado |
| 11/07 | Doc banco: [UNIOSAUDE_BANCO.md](UNIOSAUDE_BANCO.md) |

---

## Documentos relacionados

- [UNIOSAUDE_BANCO.md](UNIOSAUDE_BANCO.md) — MySQL, migrations, backup, tabelas
- [DEPLOY_MANUAL_UNIOSAUDE.md](DEPLOY_MANUAL_UNIOSAUDE.md) — passo a passo do script
- [UNIOSAUDE_DEPLOY_REPAIR.md](UNIOSAUDE_DEPLOY_REPAIR.md) — vhost HTTPS e 404
- [DNS_UNIOWORK_UNIOSAUDE.md](DNS_UNIOWORK_UNIOSAUDE.md) — DNS do subdomínio
- [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) — pipeline quando Actions voltar
- [OPERACAO_VALIDACAO_LOCAL.md](OPERACAO_VALIDACAO_LOCAL.md) — validar antes do push
