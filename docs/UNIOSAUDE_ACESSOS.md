# Unio Saúde — acessos e credenciais (produção)

**URL:** https://uniosaude.uniowork.com.br  
**Atualizado:** jul/2026 (após deploy manual)

---

## Login da plataforma (`/login`)

### Contas que funcionam hoje no servidor

O banco de produção (`joabef36_unio_clinicaunio`) ainda usa os **e-mails legados** do seed antigo.  
**Senha de todas:** `unio123`

| E-mail | Perfil | Uso recomendado |
|--------|--------|-----------------|
| **`gestor@unio.dev`** | GESTOR | **Principal** — médico/gestor clínico (Renata demo) |
| `tenant@unio.dev` | TENANT | Dono do tenant / visão ampla |
| `supervisor@unio.dev` | SUPERVISOR | Supervisor |
| `gestor.eq@unio.dev` | GESTOR_EQUIPE | Gestor de equipe |
| `sup.eq@unio.dev` | SUPERVISOR_EQUIPE | Supervisor de equipe |
| `membro@unio.dev` | MEMBRO | Membro da equipe |
| `gestor@nexus.dev` | GESTOR | Empresa demo Nexus |
| `gestor@edu360.dev` | GESTOR | Empresa demo Edu360 |

### Entrada rápida (copiar)

```
E-mail: gestor@unio.dev
Senha:  unio123
```

Após login → redireciona para `/pulso`.

### E-mails novos (`@unio.dev` com nome) — ainda não no servidor

Estes aparecem na documentação de desenvolvimento, mas **não existem** no banco de produção até rodar o seed:

| E-mail novo | Equivalente legado |
|-------------|-------------------|
| `renata.oliveira@unio.dev` | `gestor@unio.dev` |
| `joabe.nascimento@unio.dev` | `tenant@unio.dev` |
| `ricardo.costa@unio.dev` | `gestor.eq@unio.dev` |

Para criar/atualizar no servidor (uma vez, com cuidado):

```bash
cd /home2/joabef36/unio-uniosaude
php bin/console app:seed-users --allow-prod --no-interaction
php bin/console app:seed-product-grants --force --allow-prod --no-interaction
```

Depois disso, `renata.oliveira@unio.dev` / `unio123` também passará a funcionar.

### `joabe@uniowork.com.br` — não use no login demo

O navegador pode **preencher automaticamente** esse e-mail (conta real / PLATFORM_OWNER).  
Ele **não está** na tabela `user` desta instância — o login falha se a senha não coincidir.

Conta pessoal do dono (quando configurada): `joabe@uniowork.com.br` — senha definida via workflow **Setup PLATFORM_OWNER**, não é `unio123` por padrão.

---

## Portal do beneficiário (paciente)

| URL | Acesso |
|-----|--------|
| https://uniosaude.uniowork.com.br/clinica/portal/login | Identificação do paciente |
| https://uniosaude.uniowork.com.br/beneficiario/carteirinha | Carteirinha digital |

### Dados de demonstração (carteirinha)

Use **dois** identificadores diferentes nos passos 1 e 2:

| Campo | Valor demo |
|-------|------------|
| CPF | `529.982.247-25` |
| Código | `PO-0042` |
| Carteirinha | `PM-24K9X7Q1` |

Exemplo: CPF no passo 1 + código no passo 2.

---

## Rotas úteis (staff logado)

| Rota | Descrição |
|------|-----------|
| `/pulso` | Home pós-login |
| `/medico/protocolos` | Alias → protocolos pós-operatório |
| `/pos-operatorio/protocolos` | Protocolos (canônica) |
| `/clinica/portal/login` | Portal paciente |

---

## Documentos relacionados

- [UNIOSAUDE_DEPLOY_OPERACAO.md](UNIOSAUDE_DEPLOY_OPERACAO.md) — deploy manual
- [OPERACAO_INDICE.md](OPERACAO_INDICE.md) — índice geral
