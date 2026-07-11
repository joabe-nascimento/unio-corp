# Unio Saúde — acessos e credenciais (produção)

**URL:** https://uniosaude.uniowork.com.br  
**Senha demo (todas as contas abaixo):** `unio123`

Contas com **nomes reais** — sem e-mails genéricos (`gestor@`, `tenant@`, etc.).

---

## Login da plataforma (`/login`)

### Contas principais (Unio Demo)

| Nome | E-mail | Perfil | Uso |
|------|--------|--------|-----|
| **Renata Oliveira** | **`renata.oliveira@unio.dev`** | GESTOR | **Principal** — médica / pós-operatório |
| Joabe Nascimento | `joabe.nascimento@unio.dev` | TENANT | Dono do tenant / visão ampla |
| Ricardo Costa | `ricardo.costa@unio.dev` | GESTOR_EQUIPE | Gestor de equipe |
| Ana Paula Ribeiro | `ana.ribeiro@unio.dev` | SUPERVISOR | Supervisora |
| Felipe Martins | `felipe.martins@unio.dev` | SUPERVISOR_EQUIPE | Supervisor de equipe |
| Lucas Santos | `lucas.santos@unio.dev` | MEMBRO | Membro da equipe |

### Entrada rápida (copiar)

```
E-mail: renata.oliveira@unio.dev
Senha:  unio123
```

Após login → redireciona para `/pulso`.

### Outras empresas demo

| Nome | E-mail | Empresa |
|------|--------|---------|
| Marcela Ferreira | `marcela.ferreira@nexus.dev` | Nexus Saúde |
| Patrícia Almeida | `patricia.almeida@edu360.dev` | Edu360 |

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

---

## Conta pessoal do dono (não é demo)

`joabe@uniowork.com.br` — conta **PLATFORM_OWNER** (produção Unio geral).  
Não usar no login demo da instância Unio Saúde; senha não é `unio123`.

---

## Atualizar contas no servidor

Se o banco ainda tiver e-mails legados, rode no servidor:

```bash
cd /home2/joabef36/unio-uniosaude
php bin/console app:seed-users --allow-prod --no-interaction
php bin/console app:seed-product-grants --force --allow-prod --no-interaction
```

O seed **renomeia** `gestor@unio.dev` → `renata.oliveira@unio.dev` (e demais) sem apagar dados.

---

## Documentos relacionados

- [UNIOSAUDE_DEPLOY_OPERACAO.md](UNIOSAUDE_DEPLOY_OPERACAO.md)
- [OPERACAO_INDICE.md](OPERACAO_INDICE.md)
