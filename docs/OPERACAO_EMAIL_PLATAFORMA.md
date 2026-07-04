# Operação — E-mail e identidade da plataforma Unio

Guia completo: contas de e-mail HostGator/Titan, login da plataforma, assinaturas, automação no deploy e recuperação de senha.

**Domínio principal:** [uniowork.com.br](https://uniowork.com.br)  
**Provedor:** HostGator (cPanel + Titan Webmail)  
**Última atualização:** jul/2026 — commits `539b67a`, `04c292c`

---

## Índice

1. [Visão geral](#1-visão-geral)
2. [Contas de e-mail](#2-contas-de-e-mail)
3. [Credenciais atuais](#3-credenciais-atuais)
4. [Login na plataforma Unio](#4-login-na-plataforma-unio)
5. [Webmail, Outlook e celular](#5-webmail-outlook-e-celular)
6. [Assinaturas de e-mail](#6-assinaturas-de-e-mail)
7. [Automação no deploy](#7-automação-no-deploy)
8. [GitHub Secrets](#8-github-secrets)
9. [Comandos Symfony](#9-comandos-symfony)
10. [Scripts no servidor](#10-scripts-no-servidor)
11. [Redefinir senhas](#11-redefinir-senhas)
12. [Esqueci minha senha (self-service)](#12-esqueci-minha-senha-self-service)
13. [Configuração da plataforma (Admin)](#13-configuração-da-plataforma-admin)
14. [Solução de problemas](#14-solução-de-problemas)
15. [Arquivos relacionados no repositório](#15-arquivos-relacionados-no-repositório)

---

## 1. Visão geral

A Unio usa **três papéis distintos** de e-mail. Não misture senhas nem propósitos.

```
┌─────────────────────────────────────────────────────────────────┐
│  joabe@uniowork.com.br     →  Você (PLATFORM_OWNER)             │
│                               E-mail pessoal/profissional       │
│                               Login na plataforma Unio          │
├─────────────────────────────────────────────────────────────────┤
│  unio@uniowork.com.br      →  Caixa institucional               │
│                               Contato/suporte no site e rodapé  │
├─────────────────────────────────────────────────────────────────┤
│  noreply@uniowork.com.br   →  Apenas envio automático (Symfony) │
│                               Reset de senha, alertas, filas    │
└─────────────────────────────────────────────────────────────────┘
```

| Sistema | E-mail | Senha |
|---------|--------|-------|
| **Plataforma Unio** (app) | `joabe@uniowork.com.br` | Senha **do app** (ver [§3](#3-credenciais-atuais)) |
| **Webmail / Outlook** | `joabe@uniowork.com.br` | Senha **da caixa de e-mail** (diferente do app) |
| **Contato público** | `unio@uniowork.com.br` | Senha da caixa `unio@` (se configurada) |
| **E-mails automáticos** | `noreply@uniowork.com.br` | Configurada em `MAILER_DSN` no servidor — não usar para ler e-mail |

> **Regra:** a senha do **login Unio** e a senha do **e-mail IMAP** são **independentes**.

---

## 2. Contas de e-mail

### `joabe@uniowork.com.br`

- **Dono:** Joabe (conta `PLATFORM_OWNER` na Unio)
- **Uso:** trabalho diário, parceiros, HostGator, respostas profissionais
- **Login Unio:** este é o e-mail de acesso à plataforma (substitui `joabenascimento1@outlook.com`)
- **Criada/atualizada no deploy** via `scripts/setup-platform-mailboxes.sh`

### `unio@uniowork.com.br`

- **Dono:** plataforma (caixa genérica)
- **Uso:** “Fale conosco”, rodapé, suporte institucional
- **Config Admin:** campo `suporte_email` em **Admin → Configurações**
- **Assinatura:** HTML em `scripts/email-signatures/unio.html`

### `noreply@uniowork.com.br`

- **Uso exclusivo:** Symfony Mailer (`MAILER_DSN`, `MAILER_FROM_ADDRESS`)
- **Não responder** — caixa pode nem estar configurada para leitura
- Documentação de deploy: [DEPLOY_HOSTGATOR.md](DEPLOY_HOSTGATOR.md), [.env.prod.example](../.env.prod.example)

---

## 3. Credenciais atuais

> ⚠️ **Segurança:** este documento descreve senhas definidas em jul/2026.  
> **Não commite** `.env.local`, `var/secrets/` nem senhas em código.  
> Após uso, prefira guardar no gerenciador de senhas e **rotacionar** periodicamente.

### Plataforma Unio (login web)

| Campo | Valor |
|-------|--------|
| URL | https://uniowork.com.br/login |
| E-mail | `joabe@uniowork.com.br` |
| Senha | *(gerenciador de senhas / GitHub Secret `PLATFORM_OWNER_PASSWORD`)* |

Definida via GitHub Secret `PLATFORM_OWNER_PASSWORD` e aplicada no deploy por `app:ensure-platform-owner --allow-prod`.

### E-mail `joabe@` (Titan / Outlook / IMAP)

| Campo | Valor |
|-------|--------|
| E-mail | `joabe@uniowork.com.br` |
| Senha | *(gerenciador de senhas / GitHub Secret `MAILBOX_JOABE_PASSWORD`)* |
| Webmail Titan | Acesso pelo painel HostGator → E-mail → Webmail |

Definida via GitHub Secret `MAILBOX_JOABE_PASSWORD`.

### E-mail `unio@`

- Senha definida manualmente no Titan (se a caixa já existia antes do deploy)
- Opcional: GitHub Secret `MAILBOX_UNIO_PASSWORD` para o script recriar/atualizar no deploy

### Onde as senhas ficam armazenadas

| Local | Conteúdo |
|-------|----------|
| **GitHub Secrets** | `MAILBOX_JOABE_PASSWORD`, `MAILBOX_UNIO_PASSWORD`, `PLATFORM_OWNER_PASSWORD` |
| **Servidor** | `~/unio/var/secrets/mailbox-credentials.json` (chmod 600, não versionado) |
| **Servidor** | `~/unio/.env.local` → `MAILER_DSN` com senha do `noreply@` |

---

## 4. Login na plataforma Unio

1. Acesse https://uniowork.com.br/login  
2. E-mail: **`joabe@uniowork.com.br`**  
3. Senha: **senha do app** (tabela acima)  
4. Perfil esperado: **PLATFORM_OWNER** — acesso global, `/admin/operacoes`, etc.

### Migração do Outlook

A conta pessoal foi migrada automaticamente no deploy:

| Antes | Depois |
|-------|--------|
| `joabenascimento1@outlook.com` | `joabe@uniowork.com.br` |

Comando responsável: `app:platform:sync-email-identity`

---

## 5. Webmail, Outlook e celular

### Servidor de e-mail (IMAP / SMTP)

| Protocolo | Host | Porta | Criptografia |
|-----------|------|-------|--------------|
| **IMAP** (receber) | `mail.uniowork.com.br` | 993 | SSL/TLS |
| **SMTP** (enviar) | `mail.uniowork.com.br` | 587 | STARTTLS |
| **POP3** (alternativa) | `mail.uniowork.com.br` | 995 | SSL/TLS |

### Outlook (Windows / Mac)

1. **Arquivo → Adicionar conta** (ou Configurações → Contas → E-mail)  
2. E-mail: `joabe@uniowork.com.br`  
3. Senha: senha da **caixa de e-mail** (não a do app Unio)  
4. Se pedir servidor manual, use a tabela acima  
5. No Titan: **Conta & Segurança → Configurar em Outlook/Gmail** (link de ajuda HostGator)

### Titan Webmail (navegador)

- Portal HostGator → **E-mail** → **Acessar Webmail**  
- Selecione a conta `joabe@uniowork.com.br` ou `unio@uniowork.com.br`

---

## 6. Assinaturas de e-mail

Templates HTML prontos para colar no Titan (**Adicionar assinatura → botão HTML**):

| Conta | Arquivo | Nome sugerido no Titan |
|-------|---------|-------------------------|
| `joabe@` | [scripts/email-signatures/joabe.html](../scripts/email-signatures/joabe.html) | `Joabe — Padrão` |
| `unio@` | [scripts/email-signatures/unio.html](../scripts/email-signatures/unio.html) | `Unio — Padrão` |

### Conteúdo resumido — `joabe@`

- **Joabe Fonseca do Nascimento** · Fundador · Unio  
- Plataforma de Gestão de Pessoas  
- *Pessoas. Processos. Resultados.*  
- [uniowork.com.br](https://uniowork.com.br) · joabe@uniowork.com.br  

### Logo opcional na assinatura

```
https://uniowork.com.br/images/logos/unio-logotipo.png
```

---

## 7. Automação no deploy

Cada push em `production` executa [Deploy Production](../.github/workflows/deploy-production.yml) → `scripts/deploy-server.sh`.

### Ordem das etapas relacionadas a e-mail

```
Doctrine migrations
    ↓
Cache Symfony
    ↓
Symlinks public_html
    ↓
app:platform:sync-email-identity     ← migra e-mail owner + suporte_email
    ↓
app:ensure-platform-owner (se PLATFORM_OWNER_PASSWORD definido)
    ↓
setup-platform-mailboxes.sh            ← cria/atualiza joabe@ no cPanel
    ↓
app:platform-audit:record-deploy
```

### O que cada peça faz

| Peça | Função |
|------|--------|
| `PlatformSyncEmailIdentityCommand` | Migra owner de Outlook → `joabe@`; define `suporte_email` = `unio@`; `website` = `https://uniowork.com.br` |
| `EnsurePlatformOwnerCommand` | Garante conta PLATFORM_OWNER; atualiza senha se `PLATFORM_OWNER_PASSWORD` estiver definido |
| `setup-platform-mailboxes.sh` | cPanel UAPI: `Email add_pop` / `passwd_pop` para `joabe@` (e `unio@` se secret existir) |

---

## 8. GitHub Secrets

Repositório: `joabe-nascimento/unio-corp` → **Settings → Secrets and variables → Actions**

| Secret | Obrigatório | Descrição |
|--------|-------------|-----------|
| `MAILBOX_JOABE_PASSWORD` | Sim | Senha da caixa `joabe@uniowork.com.br` |
| `MAILBOX_UNIO_PASSWORD` | Não | Senha da caixa `unio@` (só se quiser provisionar via script) |
| `PLATFORM_OWNER_PASSWORD` | Condicional | Se definido, **redefine a senha do login Unio** a cada deploy |

### Criar ou atualizar secret (CLI)

```bash
gh secret set MAILBOX_JOABE_PASSWORD --body "SUA_SENHA" --repo joabe-nascimento/unio-corp
gh secret set PLATFORM_OWNER_PASSWORD --body "SUA_SENHA_APP" --repo joabe-nascimento/unio-corp
```

> **Atenção:** enquanto `PLATFORM_OWNER_PASSWORD` existir no GitHub, **cada deploy sobrescreve** a senha de login da plataforma.  
> Após estabilizar a senha desejada, **remova** esse secret ou troque por um fluxo manual / “Esqueci minha senha”.

---

## 9. Comandos Symfony

Executar no servidor (`~/unio`) ou localmente com `.env` adequado.

### Sincronizar identidade de e-mail (prod-safe)

```bash
php bin/console app:platform:sync-email-identity --env=prod
```

Opções:

- `--force-config` — sobrescreve `suporte_email` e `website` mesmo se já preenchidos

### Garantir conta PLATFORM_OWNER

```bash
php bin/console app:ensure-platform-owner \
  --allow-prod \
  --email=joabe@uniowork.com.br \
  --nome="Joabe Fonseca do Nascimento" \
  --password="NOVA_SENHA" \
  --env=prod
```

> Em produção exige `--allow-prod`.

---

## 10. Scripts no servidor

### Provisionar caixas manualmente

```bash
cd ~/unio
export MAILBOX_JOABE_PASSWORD="..."
export MAIL_DOMAIN=uniowork.com.br
bash scripts/setup-platform-mailboxes.sh
```

Credenciais salvas em: `var/secrets/mailbox-credentials.json`

### Estrutura do JSON de credenciais

```json
{
  "domain": "uniowork.com.br",
  "updated_at": "2026-07-03T...",
  "joabe": {
    "email": "joabe@uniowork.com.br",
    "password": "..."
  },
  "unio": {
    "email": "unio@uniowork.com.br",
    "password": "..."
  },
  "smtp": {
    "host": "mail.uniowork.com.br",
    "port": 587,
    "encryption": "tls"
  }
}
```

---

## 11. Redefinir senhas

### Senha do login Unio (app)

**Opção A — via deploy (automático)**

1. Defina `PLATFORM_OWNER_PASSWORD` no GitHub Secrets  
2. Push em `production`  
3. Deploy aplica `app:ensure-platform-owner --allow-prod`

**Opção B — manual no servidor**

```bash
cd ~/unio
php bin/console app:ensure-platform-owner \
  --allow-prod \
  --email=joabe@uniowork.com.br \
  --password="NOVA_SENHA" \
  --env=prod
```

**Opção C — pela interface**

Após logado: **Meu perfil → Alterar senha**

### Senha da caixa `joabe@` (e-mail)

**Opção A — cPanel**

1. cPanel → **Contas de e-mail**  
2. Selecione `joabe@uniowork.com.br` → **Gerenciar** → **Alterar senha**

**Opção B — script + secret**

1. Atualize `MAILBOX_JOABE_PASSWORD` no GitHub  
2. Deploy executa `passwd_pop` via UAPI

### Senha do `noreply@` (SMTP da aplicação)

1. Altere no cPanel (conta `noreply@`)  
2. Atualize `MAILER_DSN` em `~/unio/.env.local`  
3. Limpe cache: `php bin/console cache:clear --env=prod`

---

## 12. Esqueci minha senha (self-service)

Fluxo nativo da Unio:

1. https://uniowork.com.br/forgot-password  
2. Informe **`joabe@uniowork.com.br`**  
3. O link de reset chega na caixa `joabe@` (webmail Titan)  
4. Requer `MAILER_DSN` funcional no servidor (`noreply@`)

Implementação: `ForgotPasswordController`, `PasswordResetService`, rota `app_reset_password`.

---

## 13. Configuração da plataforma (Admin)

Campos em **Admin → Configurações → Contato & Suporte** (persistidos em `var/admin_config.json`):

| Campo | Valor esperado |
|-------|----------------|
| `suporte_email` | `unio@uniowork.com.br` |
| `website` | `https://uniowork.com.br` |
| `suporte_telefone` | (opcional) |
| `rodape_texto` | Tagline exibida no rodapé |

Exibidos em: rodapé do app, login, marketing, componente `platform_support.html.twig`.

---

## 14. Solução de problemas

### Não consigo logar na Unio

| Sintoma | Causa provável | Ação |
|---------|----------------|------|
| “Credenciais inválidas” | Confundiu senha do **app** com senha do **e-mail** | Use a senha da [§3](#3-credenciais-atuais) — coluna “Plataforma” |
| E-mail antigo | Ainda usa `@outlook.com` | Use `joabe@uniowork.com.br` |
| Senha desconhecida | — | `/forgot-password` ou redefinir via [§11](#11-redefinir-senhas) |

### Não consigo entrar no webmail / Outlook

| Sintoma | Causa provável | Ação |
|---------|----------------|------|
| Falha IMAP | Senha do app usada no Outlook | Use senha da caixa `joabe@` |
| Conta inexistente | cPanel sem caixa criada | Rodar `setup-platform-mailboxes.sh` ou criar no cPanel |
| uapi indisponível no SSH | Plano sem UAPI | Criar conta manualmente no cPanel |

### E-mail de reset não chega

1. Verificar `MAILER_DSN` em `.env.local` no servidor  
2. Testar envio: logs em `var/log/prod.log`  
3. Verificar spam na caixa `joabe@`  
4. Alternativa: redefinir senha via comando [§11](#11-redefinir-senhas)

### Deploy não criou a caixa

1. Conferir logs do deploy: artefato `deploy-report-*.txt` no GitHub Actions  
2. Verificar se `MAILBOX_JOABE_PASSWORD` está nos Secrets  
3. SSH: `cat ~/unio/var/secrets/mailbox-credentials.json`  
4. Criar manualmente no cPanel se UAPI falhar

---

## 15. Arquivos relacionados no repositório

| Arquivo | Descrição |
|---------|-----------|
| [scripts/setup-platform-mailboxes.sh](../scripts/setup-platform-mailboxes.sh) | Provisionamento cPanel |
| [scripts/deploy-server.sh](../scripts/deploy-server.sh) | Pós-deploy (identity + mailboxes) |
| [scripts/email-signatures/joabe.html](../scripts/email-signatures/joabe.html) | Assinatura Joabe |
| [scripts/email-signatures/unio.html](../scripts/email-signatures/unio.html) | Assinatura Unio |
| [src/Command/PlatformSyncEmailIdentityCommand.php](../src/Command/PlatformSyncEmailIdentityCommand.php) | Sync e-mail owner + contato |
| [src/Command/EnsurePlatformOwnerCommand.php](../src/Command/EnsurePlatformOwnerCommand.php) | Conta PLATFORM_OWNER |
| [src/Service/PasswordResetService.php](../src/Service/PasswordResetService.php) | Reset de senha por e-mail |
| [templates/components/platform_support.html.twig](../templates/components/platform_support.html.twig) | Exibição de contato |
| [.github/workflows/deploy-production.yml](../.github/workflows/deploy-production.yml) | Pipeline + secrets |
| [.env.prod.example](../.env.prod.example) | Exemplo `MAILER_DSN` / `noreply@` |

---

## Referências cruzadas

- [OPERACAO_INDICE.md](OPERACAO_INDICE.md) — índice geral de operação  
- [DEPLOY_HOSTGATOR.md](DEPLOY_HOSTGATOR.md) — deploy completo na HostGator  
- [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) — secrets SSH e CI/CD  

---

*Documento gerado para operação interna Unio — jul/2026.*
