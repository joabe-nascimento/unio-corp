# Deploy agora — checklist HostGator (joabef36)

**Documentação operacional completa (jul/2026):** [OPERACAO_INDICE.md](./OPERACAO_INDICE.md) — histórico de correções, branches, Actions e validação local.

**Deploy automático (recomendado após setup):** [DEPLOY_GITHUB_ACTIONS.md](./DEPLOY_GITHUB_ACTIONS.md) — `git push origin production` atualiza o site.

Status atual:

| Fase | Status |
|------|--------|
| 1 Domínios | Confirmar no Portal |
| 2 PC local | OK |
| 3 MySQL | OK — `joabef36_unio` |
| 4 Upload + document root | **FAZER AGORA** |
| 5 `.env.local` | **FAZER AGORA** |
| 6 Symfony no servidor | **FAZER AGORA** |
| 7 SSL + redirect | **FAZER AGORA** |

---

## A — No PC (antes do FTP)

```powershell
cd C:\projetos\huplex
powershell -ExecutionPolicy Bypass -File scripts\build-hostgator-zip.ps1
```

Gera **`unio-deploy.zip`** na raiz do projeto (~dezenas de MB). Upload desse arquivo e extrair no cPanel.

Alternativa (sem ZIP): `scripts\prepare-hostgator-upload.ps1` + FileZilla.

---

## B — Fase 4: cPanel (upload + PHP + domínio)

### B1 PHP 8.2+

cPanel → **MultiPHP Manager** → `uniowork.com.br` → PHP **8.2** ou **8.3**

Extensões: `pdo_mysql`, `intl`, `mbstring`, `zip`, `json`, `ctype`, `iconv`

### B2 Upload (Gerenciador de arquivos ou FileZilla)

1. `/home/joabef36/` → criar pasta **`unio`**
2. Enviar conteúdo de `C:\projetos\huplex` para `/home/joabef36/unio/`
3. Criar se não existir: `var/cache`, `var/log`, `public/uploads/users`

**FTP:** cPanel → Contas FTP → host `ftp.uniowork.com.br`, usuário `joabef36`

### B3 Document root

cPanel → **Domínios** → **uniowork.com.br** → Gerenciar

```
Document Root: /home/joabef36/unio/public
```

Repetir para **uniowork.online**.

---

## C — Fase 5: `.env.local` no servidor

Arquivo: **`/home/joabef36/unio/.env.local`** (raiz do projeto, não dentro de `public/`)

Gerenciador de arquivos → **+ Arquivo** → `.env.local` → colar:

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=altere-gerar-string-aleatoria-64-chars

DEFAULT_URI=https://uniowork.com.br

DATABASE_URL="mysql://USUARIO:SENHA@localhost:3306/NOME_DO_BANCO?serverVersion=10.11.2-MariaDB&charset=utf8mb4"

MAILER_DSN=smtp://noreply%40uniowork.com.br:SENHA_SMTP@smtp.titan.email:587
MAILER_FROM_ADDRESS=noreply@uniowork.com.br

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

VITORIA_AI_ENABLED=false
VITORIA_AI_URL=
VITORIA_AI_KEY=

MERCURE_URL=
MERCURE_PUBLIC_URL=
MERCURE_JWT_SECRET=
```

Permissão: **644**. Não commitar no Git.

---

## D — Fase 6: Terminal cPanel

cPanel → **Terminal**:

```bash
cd ~/unio

php -v
php bin/console about --env=prod

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-users
php bin/console app:seed-product-grants
php bin/console cache:clear --env=prod

mkdir -p var/cache var/log public/uploads/users
chmod -R ug+rwx var/
chmod -R ug+rwx public/uploads/
```

Login seed (altere depois): `renata.oliveira@unio.dev` / `unio123`

**Cron em produção:** não agende `app:seed-users`, `app:seed-product-grants` nem outros comandos `app:seed-*` — eles são bloqueados em prod e só servem para dev/staging. Mantenha no cron apenas filas e lembretes (ex.: `app:clinic:agenda-reminders`, `app:pos-operatorio:send-reminders`, `app:rh:email-process-queue`).

Se der erro 500:

```bash
tail -50 var/log/prod.log
```

---

## E — Fase 7: SSL e redirects

### E1 SSL

cPanel → **SSL/TLS Status** → **Run AutoSSL** para `uniowork.com.br` e `uniowork.online`

cPanel → **Domínios** → ativar **Force HTTPS Redirect**

### E2 Redirects

cPanel → **Redirecionamentos**:

| De | Para | Tipo |
|----|------|------|
| `uniowork.online` | `https://uniowork.com.br` | 301 |
| `www.uniowork.com.br` | `https://uniowork.com.br` | 301 |

### E3 Teste final

- [ ] https://uniowork.com.br/login abre
- [ ] Login funciona
- [ ] uniowork.online redireciona para .com.br
- [ ] Admin → Configurações → Identidade → website `https://uniowork.com.br`

---

## Ordem em uma linha

```
PC: prepare-hostgator-upload.ps1
 → cPanel: upload ~/unio/
 → document root → .../unio/public
 → .env.local
 → Terminal: migrate + seed + cache + chmod
 → SSL + redirect
 → testar /login
```

Guia completo: [DEPLOY_HOSTGATOR.md](DEPLOY_HOSTGATOR.md)

---

## Debug Symfony na HostGator (temporário)

Para ver erros detalhados e a barra de debug enquanto corrige problemas (ex.: sessão):

### 1. Editar `.env.local` no servidor

Arquivo: **`/home2/joabef36/unio/.env.local`**

```dotenv
APP_ENV=prod
APP_DEBUG=0
```

**Importante:** volte `APP_DEBUG=0` quando terminar — expõe detalhes internos do sistema.

### 2. Limpar cache

Gerenciador de arquivos → apague o conteúdo de `unio/var/cache/prod/`  
**ou** Terminal:

```bash
cd /home2/joabef36/unio
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
```

### 3. O que passa a funcionar

| Recurso | Onde |
|---------|------|
| Páginas de erro detalhadas (stack trace) | Na tela ao ocorrer 500 ou exceção não tratada |
| Log completo | `unio/var/log/prod.log` (Gerenciador de Arquivos) |
| Web Profiler (barra no rodapé) | **Só no PC local** (`APP_ENV=dev`) — não vai no ZIP de produção |

### 4. Deploy do código com suporte a debug

Gere e envie novo ZIP após atualizar o projeto:

```powershell
cd C:\projetos\huplex
powershell -ExecutionPolicy Bypass -File scripts\build-hostgator-zip.ps1
```

Upload de `unio-deploy.zip` → extrair em `/home2/joabef36/unio/`.
