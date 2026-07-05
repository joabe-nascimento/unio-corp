# Contas de login — Produção, Staging e RH homolog

Referência rápida (jul/2026). **Não commite senhas reais neste arquivo.**

Atualizar após sync: `bash scripts/sync-homolog-identity-from-prod.sh` (no servidor).

---

## URLs

| Ambiente | URL login | Branch deploy | Banco MySQL |
|----------|-----------|---------------|-------------|
| **Produção** | https://uniowork.com.br/login | `production` | `joabef36_unio` |
| **Staging** | https://staging.uniowork.com.br/login | `new_staging` | `joabef36_unio_staging` |
| **RH homolog** | https://rh.uniowork.com.br/login | `product/rh` | `joabef36_unio_rh` |

---

## Senhas

| Tipo de conta | Senha |
|---------------|--------|
| Contas `*@unio.dev`, `*@nexus.dev`, `*@edu360.dev` | `unio123` (seed padrão) |
| Contas `*@homolog.uniowork.com.br` (só RH) | **Igual ao `gestor@unio.dev`** → `unio123` |
| **`joabe@uniowork.com.br`** (PLATFORM_OWNER) | Sua senha real de produção (não fica no Git) |

Staging e RH homolog usam **as mesmas contas e hashes de senha da produção** (sync de identidade).

---

## Contas espelhadas (Prod = Staging = RH)

Presentes nos três ambientes após o sync.

| E-mail | Perfil | Empresa / workspace |
|--------|--------|---------------------|
| `joabe@uniowork.com.br` | PLATFORM_OWNER | Global (sem empresa) — admin plataforma |
| `tenant@unio.dev` | TENANT | Multi-empresa |
| `gestor@unio.dev` | GESTOR | Unio Demo |
| `gestor.eq@unio.dev` | GESTOR_EQUIPE | Unio Demo |
| `supervisor@unio.dev` | SUPERVISOR | Unio Demo |
| `sup.eq@unio.dev` | SUPERVISOR_EQUIPE | Unio Demo |
| `membro@unio.dev` | MEMBRO | Unio Demo |
| `gestor@nexus.dev` | GESTOR | Nexus Saúde S/A |
| `gestor@edu360.dev` | GESTOR | Edu360 Ensino |

### Empresas (demo / prod)

| Nome | CNPJ |
|------|------|
| Unio Demo | 11.111.111/0001-11 |
| Nexus Saúde S/A | 22.222.222/0001-22 |
| Edu360 Ensino | 33.333.333/0001-33 |

---

## Contas extras — só RH homolog

Empresas fictícias para testar o módulo RH (além do espelho da prod).

| E-mail | Perfil | Empresa |
|--------|--------|---------|
| `gestor.alpina@homolog.uniowork.com.br` | GESTOR | Alpina Logística S.A. |
| `gestor.horizonte@homolog.uniowork.com.br` | GESTOR | Horizonte Saúde Ltda |
| `gestor.meridian@homolog.uniowork.com.br` | GESTOR | Meridian Tech ME |

---

## Uso rápido para testes

| Objetivo | Onde | Login sugerido |
|----------|------|----------------|
| Admin plataforma | Prod / Staging / RH | `joabe@uniowork.com.br` |
| Gestor workspace demo | Qualquer ambiente | `gestor@unio.dev` / `unio123` |
| Membro equipe | Qualquer ambiente | `membro@unio.dev` / `unio123` |
| RH em empresa fictícia | Só RH homolog | `gestor.alpina@homolog.uniowork.com.br` / `unio123` |
| Homolog geral Unio | Staging | Mesmas contas da prod |

---

## Bancos (HostGator `joabef36`)

```
~/unio           → joabef36_unio           (produção)
~/unio-staging   → joabef36_unio_staging   (staging)
~/unio-rh        → joabef36_unio_rh        (RH homolog)
```

Credenciais MySQL: `.env.local` de cada pasta (nunca no Git).

---

## Re-sincronizar contas da prod → homologs

No servidor:

```bash
cd /home2/joabef36/unio
bash scripts/sync-homolog-identity-from-prod.sh
```

Isso sobrescreve `empresa`, `user` e `user_product_grant` em staging e RH (RH recria também as 3 empresas fictícias).
