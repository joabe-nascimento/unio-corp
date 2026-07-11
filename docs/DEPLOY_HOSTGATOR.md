# Deploy completo — Unio na HostGator

Fluxo passo a passo: o que fazer **no seu PC (projeto)** e o que fazer **na HostGator (painel/servidor)**.

Domínios:
- **Principal:** [uniowork.com.br](https://uniowork.com.br)
- **Secundário:** [uniowork.online](https://uniowork.online) → redireciona para `.com.br`

---

## Links úteis HostGator

| O quê | Link |
|-------|------|
| Site HostGator BR | https://www.hostgator.com.br |
| **Portal do Cliente** (login) | https://cliente.hostgator.com.br |
| Central de Ajuda | https://suporte.hostgator.com.br |
| Como acessar o cPanel | https://suporte.hostgator.com.br/hc/pt-br/articles/30816449693459-Como-acessar-o-cPanel |
| Tela de Sites no Portal | https://suporte.hostgator.com.br/hc/pt-br/articles/30821974204947 |
| SSL / HTTPS | cPanel → **SSL/TLS Status** ou **Let's Encrypt™ SSL** |
| Acesso cPanel pelo domínio | `https://uniowork.com.br/cpanel` |

---

## Visão do fluxo (7 fases)

```
[Fase 1] Domínios no Portal HostGator
    ↓
[Fase 2] Preparar projeto no PC (AQUI)
    ↓
[Fase 3] Banco MySQL no cPanel (LÁ)
    ↓
[Fase 4] Subir arquivos (FTP ou SSH)
    ↓
[Fase 5] Configurar .env.local no servidor (LÁ)
    ↓
[Fase 6] Comandos Symfony no servidor (LÁ)
    ↓
[Fase 7] SSL, redirects, teste final
```

---

## Fase 1 — Domínios (só na HostGator)

**Onde:** [Portal do Cliente](https://cliente.hostgator.com.br) → **Sites** → **cPanel**

| Passo | O que fazer |
|-------|-------------|
| 1.1 | Confirme que **uniowork.com.br** e **uniowork.online** aparecem em **Sites** |
| 1.2 | Se o site foi criado com domínio temporário, em **Domínios** adicione **uniowork.com.br** como principal |
| 1.3 | Anote usuário e senha do **cPanel** (e-mail de boas-vindas da HostGator) |
| 1.4 | Verifique se seu plano tem **SSH** (Business/Turbo+) — facilita muito |

**Ainda não mexe no código.**

---

## Fase 2 — Preparar o projeto (AQUI no PC)

Pasta do projeto: `C:\projetos\huplex`

### 2.1 Conferir que está tudo ok localmente

```powershell
cd C:\projetos\huplex
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-users
npm install
npm run vendor:sync
```

Abra localmente e teste login.

### 2.2 Gerar segredo de produção

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copie o resultado — será o `APP_SECRET` no servidor.

### 2.3 Preparar cache para HostGator (sem Redis)

Hospedagem compartilhada **não tem Redis**. Edite **AQUI**:

**Arquivo:** `config/packages/prod/cache.yaml`

Substitua o conteúdo por:

```yaml
framework:
    cache:
        app: cache.adapter.filesystem
```

> Quando tiver VPS + Redis no futuro, volte para `cache.adapter.redis`.

### 2.4 Montar pacote para envio

**Opção A — Git (se o repositório estiver no GitHub/GitLab):**  
Só faça commit das alterações (incl. `public/.htaccess`). No servidor você dá `git clone`.

**Opção B — FTP (sem SSH):**

```powershell
cd C:\projetos\huplex
composer install --no-dev --optimize-autoloader
npm ci
npm run vendor:sync
```

Envie **tudo**, exceto:
- `node_modules/`
- `.env.local` (segredos locais)
- `var/cache/*` e `var/log/*`
- `.git/` (opcional)

Inclua: `vendor/`, `public/`, `src/`, `config/`, `templates/`, `migrations/`, `bin/`, `composer.json`, `symfony.lock`

### 2.5 Arquivo de ambiente (modelo)

Use [`.env.prod.example`](../.env.prod.example) como base. Você **não** envia `.env.local` do PC — cria outro **no servidor** na Fase 5.

---

## Fase 3 — Banco MySQL (LÁ no cPanel)

**Onde:** cPanel → **MySQL® Database Wizard** ou **Bancos de dados MySQL**

| Passo | Ação |
|-------|------|
| 3.1 | Criar banco, ex.: `seuuser_unio` |
| 3.2 | Criar usuário MySQL com senha forte |
| 3.3 | Associar usuário ao banco com **ALL PRIVILEGES** |
| 3.4 | Anote: **host** (`localhost`), **nome do banco**, **usuário**, **senha** |

Monte a URL (para o `.env.local`):

```
mysql://USUARIO:SENHA@localhost:3306/NOME_DO_BANCO?serverVersion=10.11.2-MariaDB&charset=utf8mb4
```

---

## Fase 4 — Subir arquivos e apontar domínio (LÁ)

### 4.1 Estrutura correta (importante)

```
/home/SEU_USUARIO_CPANEL/
  unio/                 ← projeto Symfony (pasta inteira)
    public/             ← Document Root aponta AQUI
      index.php
      .htaccess
    src/
    config/
    vendor/
    var/
    ...
```

**Nunca** coloque `.env` ou `src/` diretamente em `public_html` aberto.

### 4.2 Enviar arquivos

**Com SSH** (Terminal do cPanel ou PuTTY):

```bash
cd ~
git clone https://github.com/SEU_USUARIO/SEU_REPO.git unio
cd unio
composer install --no-dev --optimize-autoloader
```

**Com FTP** (FileZilla — credenciais em cPanel → **Contas FTP**):

- Host: `ftp.uniowork.com.br` ou IP do servidor
- Envie para `/home/usuario/unio/`

### 4.3 Document Root do domínio

**Onde:** cPanel → **Domínios** → **uniowork.com.br** → **Gerenciar** / **Document Root**

| Domínio | Document Root |
|---------|---------------|
| uniowork.com.br | `/home/SEU_USUARIO/unio/public` |
| uniowork.online | mesmo path (depois redirect) |

### 4.4 PHP 8.2+

**Onde:** cPanel → **Select PHP Version** ou **MultiPHP Manager**

- Selecione **PHP 8.2** ou **8.3**
- Extensões obrigatórias: `pdo_mysql`, `intl`, `mbstring`, `zip`, `json`, `ctype`, `iconv`

---

## Fase 5 — `.env.local` no servidor (LÁ)

**Onde:** cPanel → **Gerenciador de arquivos** → `/home/usuario/unio/.env.local`  
(ou `nano .env.local` via SSH)

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=COLE_O_SECRET_GERADO_NA_FASE_2

DEFAULT_URI=https://uniowork.com.br

DATABASE_URL="mysql://USUARIO:SENHA@localhost:3306/NOME_BANCO?serverVersion=10.11.2-MariaDB&charset=utf8mb4"

MAILER_DSN=smtp://noreply%40uniowork.com.br:SENHA_EMAIL@mail.uniowork.com.br:587
MAILER_FROM_ADDRESS=noreply@uniowork.com.br

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

VITORIA_AI_ENABLED=false
MERCURE_URL=
MERCURE_PUBLIC_URL=
MERCURE_JWT_SECRET=
```

Salve. **Não commite** este arquivo no Git.

---

## Fase 6 — Comandos Symfony (LÁ)

**Onde:** cPanel → **Terminal** ou SSH

```bash
cd ~/unio

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-users
php bin/console app:seed-product-grants
php bin/console cache:clear --env=prod

chmod -R ug+rwx var/
chmod -R ug+rwx public/uploads/
```

Login padrão seed (altere depois): ver saída de `app:seed-users` (ex. `renata.oliveira@unio.dev` / `unio123`).

---

## Fase 7 — SSL, redirects e ajustes finais (LÁ)

### 7.1 SSL (HTTPS)

cPanel → **SSL/TLS Status** → **Run AutoSSL** para `uniowork.com.br` e `uniowork.online`

cPanel → **Domínios** → ativar **Force HTTPS Redirect**

### 7.2 Redirect uniowork.online → .com.br

cPanel → **Redirecionamentos**

| De | Para | Tipo |
|----|------|------|
| `uniowork.online` | `https://uniowork.com.br` | Permanente (301) |
| `www.uniowork.com.br` | `https://uniowork.com.br` | Permanente (301) |

### 7.3 E-mail (opcional mas recomendado)

cPanel → **Contas de e-mail** → criar:
- `noreply@uniowork.com.br`
- `suporte@uniowork.com.br`

Use essas credenciais no `MAILER_DSN`.

### 7.4 Configurar identidade na plataforma (LÁ no site)

1. Acesse `https://uniowork.com.br/login`
2. Entre como tenant/gestor
3. **Admin → Configurações → Identidade**
4. Website: `https://uniowork.com.br`
5. Salvar configurações

---

## Checklist final

### No PC (AQUI)
- [ ] Projeto roda local
- [ ] `config/packages/prod/cache.yaml` = filesystem
- [ ] `public/.htaccess` existe
- [ ] `APP_SECRET` gerado
- [ ] Código no Git ou pacote FTP pronto

### Na HostGator (LÁ)
- [ ] Banco MySQL criado
- [ ] Arquivos em `~/unio/`
- [ ] Document root = `~/unio/public`
- [ ] PHP 8.2+
- [ ] `.env.local` de produção
- [ ] Migrations executadas
- [ ] SSL ativo
- [ ] Redirect `.online` → `.com.br`
- [ ] `https://uniowork.com.br/login` abre

---

## Resumo AQUI vs LÁ

| Etapa | AQUI (seu PC) | LÁ (HostGator) |
|-------|---------------|----------------|
| Domínios | — | Portal + cPanel |
| Código | Git/FTP pack, cache filesystem | Upload / git clone |
| Banco | — | Criar MySQL |
| `.env` | Só modelo | Criar `.env.local` real |
| Composer | `install --no-dev` (local ou SSH) | `migrate`, `cache:clear` |
| DNS/SSL | — | AutoSSL + redirects |
| Teste | localhost | uniowork.com.br |

---

## Problemas comuns

| Problema | Solução |
|----------|---------|
| 403/404 nas rotas | Document root deve ser `public/`; conferir `.htaccess` |
| 500 Internal Error | Ver `var/log/prod.log`; permissão em `var/` |
| Página em branco | `APP_DEBUG=0` — veja log; PHP 8.2 ativo? |
| Erro Redis | Usar cache filesystem (Fase 2.3) |
| CSS quebrado | Rodar `npm run vendor:sync` antes do upload |
| E-mail não envia | Conferir `MAILER_DSN` e conta criada no cPanel |

---

## Próximos passos (depois que estiver no ar)

- Trocar senhas dos usuários seed
- Configurar backup (cPanel → Backup)
- Subdomínio `app.uniowork.com.br` (opcional, mesma pasta `public/`)
- VPS + Redis quando o tráfego crescer
