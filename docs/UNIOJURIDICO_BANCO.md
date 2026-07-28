# Unio Jurídico — banco de dados (jul/2026)

Referência do MySQL/MariaDB da instância **uniojuridico** (UnioJurídico): conexão, migrations, backup e tabelas de clientes/processos.

---

## Conexões por ambiente

| Ambiente | Banco (exemplo) | Config |
|----------|-----------------|--------|
| **Produção** | `joabef36_unio_uniojuridico` | `~/unio-uniojuridico/.env.local` → `DATABASE_URL` |
| **Dev local** | `unio` (ou conforme `.env`) | `.env` + `.env.local` |
| **Testes** | conforme `.env.test` | PHPUnit |

> **Setup inicial (jul/2026):** O banco de produção será provisionado no cPanel MySQL como `joabef36_unio_uniojuridico`. Crie um usuário MySQL dedicado e configure as permissões para este schema.

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

### Rodar só no servidor (SSH)

```bash
ssh -p 2222 -i ~/.ssh/unio_deploy joabef36@br1136.hostgator.com.br
cd /home2/joabef36/unio-uniojuridico
php bin/console doctrine:migrations:migrate --no-interaction
```

Ou use o deploy manual completo (recomendado — envia código + migrate + cache):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniojuridico-manual.ps1
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
cd /home2/joabef36/unio-uniojuridico
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

## Tabelas — estrutura prevista

O Unio Jurídico herda as tabelas base (User, Empresa, Convite, Membro, Workspace) e **não utiliza** as tabelas específicas de clínica (`pos_operatorio_*`, `clinic_*`).

Tabelas principais esperadas para o módulo jurídico (a serem implementadas conforme roadmap):

- **Cliente** (pessoa física/jurídica atendida pelo escritório)
- **Processo** (número CNJ, tribunal, vara, fase, valor da causa)
- **Prazo** (tipo, vencimento, processo_id, alerta_enviado)
- **Documento** (petição, contrato, procuração — upload/storage)
- **Honorário** (parcelas, status pagamento, processo_id)
- **Audiência** (data, hora, local, processo_id)

> A estrutura de RAG (base de conhecimento por escritório) está no **JurisFlow AI Service** (Python/FAISS/LangChain), não no MySQL — cada `escritorio_id` tem seu próprio vetor store.

---

## Verificação pós-migrate

```bash
php bin/console doctrine:migrations:status
# New: 0 | Current: Version...

php bin/console dbal:run-sql "SHOW TABLES"
```

---

## Testes funcionais (produção)

Após deploy em https://uniojuridico.uniowork.com.br:

| Fluxo | URL |
|-------|-----|
| Login staff | `/login` |
| Pulso (dashboard) | `/pulso` |
| Chat IA (Sasha) | `/pulso` (botão Lumen no toolbar) |
| Admin | `/admin` |
| Clientes | `/clientes` (a implementar) |
| Processos | `/processos` (a implementar) |
| Prazos | `/prazos` (a implementar) |

**Login inicial:** conforme configurado no seed ou via `/register` (se `registro_publico` estiver habilitado).

---

## Branding legado (`admin_config.json`)

No deploy, `scripts/lib/migrate-legacy-branding.php` atualiza taglines antigas para o slogan do ambiente (`UNIO_ORGANISMO_BRAND_SLOGAN`, padrão *Justiça que acompanha.*). Log esperado:

```text
migrate-legacy-branding: atualizado plataforma_tagline -> Justiça que acompanha.
```

ou `nada a alterar` se já estiver correto.

---

## Documentos relacionados

- [DEPLOY_MANUAL_UNIOJURIDICO.md](DEPLOY_MANUAL_UNIOJURIDICO.md) — deploy manual passo a passo
- [UNIOJURIDICO_ACESSOS.md](UNIOJURIDICO_ACESSOS.md) — logins e portal
- [docs/uniojuridico/README.md](uniojuridico/README.md) — arquitetura e integração de IA
- [OPERACAO_INDICE.md](OPERACAO_INDICE.md) — índice geral
