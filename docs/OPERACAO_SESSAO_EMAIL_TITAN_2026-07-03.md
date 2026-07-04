# Sessão de operação — E-mail Titan e envio automático (3 jul 2026)

Registro do que foi configurado, corrigido e validado em produção na HostGator/Titan para a plataforma Unio (`uniowork.com.br`).

**Domínio:** [uniowork.com.br](https://uniowork.com.br)  
**Servidor:** HostGator Plano M · SSH porta `2222` · usuário `joabef36`  
**Repositório:** `joabe-nascimento/unio-corp`

> **Segurança:** este documento **não contém senhas**. Valores sensíveis ficam no gerenciador de senhas, em `~/unio/.env.local` no servidor ou nos secrets do GitHub (quando aplicável).

---

## Índice

1. [Objetivo](#1-objetivo)
2. [Contexto antes da sessão](#2-contexto-antes-da-sessão)
3. [O que foi feito](#3-o-que-foi-feito)
4. [Configuração final de e-mail](#4-configuração-final-de-e-mail)
5. [DNS (estado final)](#5-dns-estado-final)
6. [Aplicação Symfony (MAILER e fila)](#6-aplicação-symfony-mailer-e-fila)
7. [Problemas encontrados e soluções](#7-problemas-encontrados-e-soluções)
8. [Validações realizadas](#8-validações-realizadas)
9. [Estado atual — checklist](#9-estado-atual--checklist)
10. [Pendências opcionais](#10-pendências-opcionais)
11. [Comandos úteis](#11-comandos-úteis)
12. [Referências](#12-referências)

---

## 1. Objetivo

Colocar em produção o envio automático de e-mails da Unio (reset de senha, notificações via Symfony Mailer), migrando o e-mail do domínio para **Titan (HostGator)** e eliminando conflitos com o e-mail antigo do **cPanel**.

---

## 2. Contexto antes da sessão

| Item | Situação |
|------|----------|
| Produção | `https://uniowork.com.br` — branch `production`, deploy via GitHub Actions |
| Homolog RH | `https://rh.uniowork.com.br` — branch `product/rh`, isolado |
| E-mail | Contas mistas cPanel + Titan; `MAILER_DSN` incompleto ou incorreto |
| Reset de senha | UI mostrava sucesso, mas e-mail não chegava |
| GitHub Secrets | `PLATFORM_OWNER_PASSWORD` e `MAILBOX_JOABE_PASSWORD` recriavam contas/senhas a cada deploy |

---

## 3. O que foi feito

### 3.1 GitHub Secrets

**Removidos** (para o deploy não sobrescrever mais configuração manual):

| Secret | Motivo |
|--------|--------|
| `PLATFORM_OWNER_PASSWORD` | Evitava reset da senha de login a cada deploy |
| `MAILBOX_JOABE_PASSWORD` | Evitava recriar `joabe@` no cPanel via `setup-platform-mailboxes.sh` |

**Mantidos:** `DEPLOY_SSH_*` (4 secrets de deploy).

### 3.2 Contas Titan criadas/configuradas

| Conta | Uso |
|-------|-----|
| `joabe@uniowork.com.br` | Caixa pessoal + identidade do owner (Titan webmail) |
| `noreply@uniowork.com.br` | Envio automático Symfony (`MAILER_DSN`) |
| `unio@uniowork.com.br` | Contato institucional |

**Configurações Titan aplicadas:**

- **Conectar domínio:** MX e SPF verificados
- **Enable Titan on other apps** habilitado em `noreply@` (SMTP externo)
- **Reputação de e-mail → Adicionar registro DKIM:** chave `titan1._domainkey` gerada e **verificada**

### 3.3 Migração joabe@ (cPanel → Titan)

- `joabe@` passou a existir no **Titan** (webmail)
- Conta antiga no **cPanel** deve ser **removida manualmente** se ainda existir (evita duplicata e confusão de senhas)
- Login da **plataforma Unio** usa `joabe@uniowork.com.br` — senha do **app**, independente da senha da caixa Titan

### 3.4 MAILER_DSN em produção

Arquivo: `~/unio/.env.local`

```dotenv
MAILER_FROM_ADDRESS=noreply@uniowork.com.br
MAILER_DSN="smtp://noreply%40uniowork.com.br:SENHA_URL_ENCODED@smtp.titan.email:587"
```

**Pontos críticos:**

| Regra | Detalhe |
|-------|---------|
| Host SMTP | `smtp.titan.email:587` (não usar `mail.uniowork.com.br` para contas Titan) |
| Senha com `#` | Codificar como `%23` **uma vez** dentro das aspas (`Unio2026%23`, não `%%23`) |
| `%40` | `@` do e-mail na URL do DSN |

Backup criado no servidor antes de edições: `.env.local.bak-mailer-*`

### 3.5 DKIM — limpeza DNS

Problemas encontrados na Zona DNS (`Domínios → Configurar Domínio → Editar Zona Avançada de DNS`):

| Registro | Problema | Ação |
|----------|----------|------|
| `default._domainkey.uniowork.com.br` | DKIM antigo do **cPanel** | **Excluído** |
| `titan1._domainkey.uniowork.com.br` | **2 TXT duplicados** com chaves diferentes | Mantido **apenas 1** — o valor copiado de **Reputação de e-mail** no Titan |

**Onde gerar/copiar a chave correta no painel HostGator (PT-BR):**

```
E-mails → Gerenciar e-mails → Reputação de e-mail → Adicionar registro DKIM
```

(Não confundir com **Conectar domínio**, que valida só MX/SPF.)

Após colar na Zona DNS: marcar *“Adicionei registros TXT…”* → **Verificar alterações**.

**Status final Titan (3 jul 2026, ~22:30 BRT):** Registro DKIM **Verificado**, reputação **Boa**.

### 3.6 Forgot-password e fila Messenger (causa do “não chegou no deploy”)

O fluxo `/forgot-password` usa `PasswordResetService` → `MailerInterface::send()`, que no Symfony roteia `SendEmailMessage` para transporte **`async`** (Doctrine, tabela `messenger_messages`).

| Comando | Comportamento |
|---------|---------------|
| `php bin/console mailer:test` | Envio **síncrono** na CLI → chegou na caixa |
| Forgot-password via web | Enfileira em **`async`** → **não envia** sem worker |

**Correção aplicada no servidor:**

1. Processamento manual da fila: `messenger:consume async` (4 mensagens pendentes enviadas)
2. **Cron a cada 1 minuto** para processar a fila automaticamente:

```cron
* * * * * cd /home2/joabef36/unio && /usr/local/bin/php bin/console messenger:consume async --limit=20 --time-limit=55 --env=prod >> /home2/joabef36/unio/var/log/messenger-cron.log 2>&1
```

Log do cron: `~/unio/var/log/messenger-cron.log`

---

## 4. Configuração final de e-mail

### Papéis (não misturar)

```
joabe@uniowork.com.br   →  Owner / login Unio + caixa pessoal (Titan)
unio@uniowork.com.br    →  Contato institucional (Titan)
noreply@uniowork.com.br →  Apenas envio automático (MAILER_DSN)
```

| Sistema | E-mail | Senha |
|---------|--------|-------|
| Login Unio (app) | `joabe@uniowork.com.br` | Senha do **app** (definida via reset ou `ensure-platform-owner`) |
| Webmail Titan | `joabe@uniowork.com.br` | Senha da **caixa Titan** (independente do app) |
| SMTP automático | `noreply@uniowork.com.br` | Em `MAILER_DSN` no servidor |

### Onde ficam os segredos

| Local | Conteúdo |
|-------|----------|
| `~/unio/.env.local` | `MAILER_DSN`, `DATABASE_URL`, etc. |
| Gerenciador de senhas | Senhas Titan, MySQL, login app |
| GitHub Secrets | Apenas `DEPLOY_SSH_*` (deploy) |

---

## 5. DNS (estado final)

Registros relevantes para e-mail em `uniowork.com.br`:

| Tipo | Nome | Valor / destino |
|------|------|-----------------|
| MX | `uniowork.com.br` | `mx1.titan.email` (10), `mx2.titan.email` (20) |
| TXT | `uniowork.com.br` | `v=spf1 include:spf.titan.email ~all` |
| TXT | `titan1._domainkey.uniowork.com.br` | **1 registro** — chave DKIM do Titan (Reputação de e-mail) |
| — | `default._domainkey` | **Não deve existir** |

**DMARC:** ainda **não configurado** (opcional). Ver [§10](#10-pendências-opcionais).

**Verificação:**

```bash
dig @ns1136.hostgator.com.br MX uniowork.com.br +short
dig @ns1136.hostgator.com.br TXT uniowork.com.br +short
dig @ns1136.hostgator.com.br TXT titan1._domainkey.uniowork.com.br +short
```

---

## 6. Aplicação Symfony (MAILER e fila)

### Rotas e classes

| Peça | Caminho |
|------|---------|
| Forgot password | `src/Controller/Auth/ForgotPasswordController.php` |
| Reset password | `src/Controller/Auth/ResetPasswordController.php` |
| Serviço | `src/Service/PasswordResetService.php` |
| Roteamento async | `config/packages/messenger.yaml` → `SendEmailMessage: async` |

### Comportamento da UI

A mensagem *“Se o e-mail estiver cadastrado, você receberá instruções…”* aparece **sempre** após submit válido (por segurança, não revela se o e-mail existe). Isso **não prova** que o e-mail foi enviado — apenas que o pedido foi aceito e enfileirado.

### Fluxo completo (produção)

```
Usuário → /forgot-password
       → PasswordResetService::requestReset()
       → messenger (async) → messenger_messages
       → cron (1 min) → messenger:consume
       → SMTP Titan (noreply@)
       → caixa joabe@ (Titan webmail)
```

---

## 7. Problemas encontrados e soluções

| Erro / sintoma | Causa | Solução |
|----------------|-------|---------|
| SMTP `535` auth failed | Senha errada, `%23` duplicado (`%%23`), ou “other apps” desabilitado no Titan | Senha correta, `%23` único, host `smtp.titan.email:587` |
| `550` sender rejected | `--from` incorreto no teste | Usar `--from=noreply@uniowork.com.br` |
| `550` more than one txt record (DKIM) | 2+ registros `titan1._domainkey` ou `default._domainkey` duplicado | Apagar duplicatas; manter só chave do Titan |
| `550` invalid dkim key format | Chave errada mantida ou `default._domainkey` do cPanel | Apagar `default._domainkey`; usar valor de Reputação de e-mail |
| UI ok, e-mail não chega (web) | Fila `async` sem worker/cron | `messenger:consume` + cron |
| `mailer:test` ok, forgot-password não | CLI síncrono vs web assíncrono | Cron (ou enviar mail sync em prod — não implementado) |

---

## 8. Validações realizadas

| Teste | Resultado | Horário (BRT, 3 jul) |
|-------|-----------|----------------------|
| Titan Conectar domínio (MX + SPF) | Verificado | ~22:09 |
| Titan Reputação de e-mail (DKIM) | Verificado | ~22:30 |
| `mailer:test` → `joabe@` | E-mails na caixa (Testing transport, Teste Unio, etc.) | ~22:32–22:34 |
| `/forgot-password` → `joabe@` | E-mail **Redefinição de senha — Unio** com link válido | ~22:42, ~22:56 |
| Fila `async` / `failed` | 0 / 0 após processamento | ~22:43+ |
| Cron messenger | Instalado | ~22:43 |

**URLs de teste:**

- Login: https://uniowork.com.br/login  
- Forgot password: https://uniowork.com.br/forgot-password  
- Webmail Titan: https://titan.hostgator.com.br/mail/ ou https://app.titan.email  

---

## 9. Estado atual — checklist

| Item | Status |
|------|--------|
| Produção `uniowork.com.br` | OK |
| Homolog RH `rh.uniowork.com.br` | OK (sem e-mail real — ver pendências) |
| Titan MX / SPF / DKIM | OK |
| `MAILER_DSN` produção | OK |
| Reset de senha (e-mail + link) | OK |
| Cron fila Messenger | OK |
| GitHub secrets de e-mail removidos | OK |

**Produção está pronta** para login, reset de senha e envios automáticos via `noreply@`.

---

## 10. Pendências opcionais

| Item | Situação | Recomendação |
|------|----------|--------------|
| **Homolog RH** | `MAILER_DSN=null://null` em `~/unio-rh/.env.local` | Manter até precisar testar e-mail no RH; ou reutilizar `noreply@` da prod |
| **Docs com senhas** | `OPERACAO_EMAIL_PLATAFORMA.md` etc. podem ter senhas antigas | Redigir antes de commitar |
| **DMARC** | Sem registro `_dmarc.uniowork.com.br` | TXT sugerido: `v=DMARC1; p=none; rua=mailto:joabe@uniowork.com.br; pct=100; adkim=s; aspf=s` |
| **joabe@ no cPanel** | Pode ainda existir conta legada | Remover no cPanel se existir (`uapi Email list_pops`) |
| **Cron no deploy** | Configurado manualmente no servidor | Considerar script/documentação para recriar após migração de conta |

---

## 11. Comandos úteis

### SSH

```bash
ssh -i ~/.ssh/unio_deploy -p 2222 joabef36@uniowork.com.br
cd ~/unio
```

### Mailer

```bash
grep ^MAILER .env.local
php bin/console cache:clear --env=prod
php bin/console mailer:test joabe@uniowork.com.br --from=noreply@uniowork.com.br --env=prod
```

### Fila Messenger

```bash
php bin/console messenger:stats --env=prod
php bin/console messenger:consume async --limit=20 --time-limit=55 --env=prod
tail -f var/log/messenger-cron.log
crontab -l
```

### DNS

```bash
dig @ns1136.hostgator.com.br TXT titan1._domainkey.uniowork.com.br +short
dig @ns1136.hostgator.com.br TXT default._domainkey.uniowork.com.br +short   # deve estar vazio
```

### cPanel — listar contas de e-mail legadas

```bash
uapi Email list_pops
```

---

## 12. Referências

| Recurso | URL |
|---------|-----|
| DKIM HostGator (PT) | https://suporte.hostgator.com.br/hc/pt-br/articles/43425989542931-Como-configurar-o-DKIM |
| Configurar domínio Titan (PT) | https://suporte.hostgator.com.br/hc/pt-br/articles/30811264809235 |
| Custom DKIM Titan (EN) | https://support.titan.email/hc/en-us/articles/6936688195737 |
| Docs operação relacionados | [OPERACAO_EMAIL_PLATAFORMA.md](OPERACAO_EMAIL_PLATAFORMA.md), [OPERACAO_HOMOLOG_PRODUTO.md](OPERACAO_HOMOLOG_PRODUTO.md), [OPERACAO_INDICE.md](OPERACAO_INDICE.md) |

---

*Documento gerado em 3 de julho de 2026 — sessão de configuração e-mail Titan + envio automático Unio.*
