# Unio Saúde — banco de dados (jul/2026)

Referência do MySQL/MariaDB da instância **uniosaude** (UnioClínica): conexão, migrations, backup e tabelas dos produtos beneficiário.

---

## Conexões por ambiente

| Ambiente | Banco (exemplo) | Config |
|----------|-----------------|--------|
| **Produção** | `joabef36_unio_clinicaunio` | `~/unio-uniosaude/.env.local` → `DATABASE_URL` |
| **Dev local** | `unio` (ou conforme `.env`) | `.env` + `.env.local` |
| **Testes** | conforme `.env.test` | PHPUnit |

A URL segue o padrão Symfony:

```env
DATABASE_URL="mysql://USUARIO:SENHA@localhost:3306/NOME_DO_BANCO?serverVersion=5.7.44&charset=utf8mb4"
```

No servidor HostGator o host costuma ser `localhost` e a versão `5.7.x` MariaDB.

---

## Migrations

### Comandos

```bash
# Status
php bin/console doctrine:migrations:status

# Aplicar pendentes (deploy e manual)
php bin/console doctrine:migrations:migrate --no-interaction
```

No deploy (`scripts/deploy-server.sh`), o backup roda **antes** do migrate quando o schema base já existe.

### Versão atual (jul/2026)

Última migration: **`Version20260711220000`**

| Migration | Descrição |
|-----------|-----------|
| `Version20260711160000` | `pos_operatorio_paciente.cpf` — validação carteirinha |
| `Version20260711200000` | `clinic_empresa_config`, `data_nascimento`, convite portal (`portal_invite_*`) |
| `Version20260711210000` | Comprovante: `comprovante_verificacao`, `comprovante_emitida_em`, `comprovante_valido_ate` |
| `Version20260711220000` | Branding/limites/onboarding por clínica, auditoria, check-in, unidades, API, dependentes |

### Rodar só no servidor (SSH)

```bash
ssh -p 2222 -i ~/.ssh/unio_deploy joabef36@br1136.hostgator.com.br
cd /home2/joabef36/unio-uniosaude
php bin/console doctrine:migrations:migrate --no-interaction
```

Ou use o deploy manual completo (recomendado — envia código + migrate + cache):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1
```

---

## Backup automático (pré-migration)

Script: `scripts/lib/backup-database.sh`

| Item | Valor |
|------|--------|
| **Quando** | Antes de `doctrine:migrations:migrate` no deploy |
| **Onde** | `var/backups/db/pre-migrate-YYYYMMDD-HHMMSS.sql.gz` |
| **Retenção** | Últimos **7** arquivos |
| **Ferramenta** | `mysqldump` + `gzip` |

### Privilégio PROCESS / tablespaces (HostGator)

Em contas compartilhadas, `mysqldump` pode exibir:

```text
Access denied; you need PROCESS privilege(s) for this operation when trying to dump tablespaces
```

O script usa **`--no-tablespaces`** quando o cliente mysqldump suporta a flag — o dump **continua válido** sem esse privilégio. A mensagem não deve mais aparecer no log de deploy.

### Backup manual

```bash
cd /home2/joabef36/unio-uniosaude
eval "$(php scripts/lib/print-db-mysql-params.php .)"
mysqldump --no-tablespaces -h"$DB_MYSQL_HOST" -P"$DB_MYSQL_PORT" -u"$DB_MYSQL_USER" -p"$DB_MYSQL_PASS" \
  --single-transaction --quick "$DB_MYSQL_NAME" | gzip > var/backups/db/manual-$(date +%Y%m%d).sql.gz
```

### Restaurar (cuidado)

```bash
gunzip -c var/backups/db/pre-migrate-XXXXXXXX.sql.gz | mysql -h HOST -u USER -p NOME_DO_BANCO
```

Teste primeiro em homologação. Restaurar em produção sobrescreve dados.

---

## Tabelas — produtos beneficiário (nível carteirinha)

### `pos_operatorio_paciente` (campos relevantes)

| Coluna | Uso |
|--------|-----|
| `codigo` | Código do paciente (ex.: PO-0042) |
| `cpf` | Validação portal beneficiário |
| `data_nascimento` | Cadastro / ficha |
| `carteirinha_verificacao` | Código público carteirinha |
| `carteirinha_valida_ate` | Validade emissão |
| `comprovante_verificacao` | Código público comprovante (único global) |
| `comprovante_valido_ate` | Validade comprovante |
| `portal_invite_token` | Convite portal paciente |
| `portal_invite_expires_at` | Expiração do convite |

### `clinic_empresa_config`

Config por clínica (`empresa_id`): produtos ativos, guias médicos, integrações (JSON). Substitui persistência só em arquivo para toggles da plataforma clínica.

### Índices únicos importantes

- `UNIQ_POSOP_PAC_CPF` — `(empresa_id, cpf)`
- `UNIQ_POSOP_PORTAL_INVITE` — `portal_invite_token`
- `UNIQ_POSOP_COMPROVANTE_VERIF` — `comprovante_verificacao`
- Código carteirinha — único por emissão (campo `carteirinha_verificacao`)

---

## Verificação pós-migrate

```bash
php bin/console doctrine:migrations:status
# New: 0 | Current: Version20260711210000

php bin/console dbal:run-sql "SHOW COLUMNS FROM pos_operatorio_paciente LIKE 'comprovante_%'"
```

---

## Testes funcionais (produção)

Após deploy em https://uniosaude.uniowork.com.br:

| Fluxo | URL |
|-------|-----|
| Login staff | `/login` |
| Hub paciente | `/paciente` |
| Carteirinha beneficiário | `/carteirinha-digital` |
| Comprovante | `/comprovante-procedimento` |
| Validação QR (público) | `/verificar/{codigo}` |
| Guia médico | `/guia-medico` |
| Portal pós-op | `/clinica/portal/login` |

**Demo carteirinha:** CPF `529.982.247-25` + código paciente `PO-0042` + código carteirinha (dois passos). Ver [UNIOSAUDE_ACESSOS.md](UNIOSAUDE_ACESSOS.md).

---

## Branding legado (`admin_config.json`)

No deploy, `scripts/lib/migrate-legacy-branding.php` atualiza taglines antigas (`Cuidado que continua.`) para o slogan do ambiente (`UNIO_ORGANISMO_BRAND_SLOGAN`, padrão *Saúde que acompanha.*). Log esperado:

```text
migrate-legacy-branding: atualizado plataforma_tagline -> Saúde que acompanha.
```

ou `nada a alterar` se já estiver correto.

---

## Documentos relacionados

- [UNIOSAUDE_DEPLOY_OPERACAO.md](UNIOSAUDE_DEPLOY_OPERACAO.md) — deploy manual
- [DEPLOY_MANUAL_UNIOSAUDE.md](DEPLOY_MANUAL_UNIOSAUDE.md) — script passo a passo
- [UNIOSAUDE_ACESSOS.md](UNIOSAUDE_ACESSOS.md) — logins e portal beneficiário
- [OPERACAO_INDICE.md](OPERACAO_INDICE.md) — índice geral
