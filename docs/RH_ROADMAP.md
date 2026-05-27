# RH Roadmap — Status por fase

Documento de acompanhamento do roadmap RH na branch `product/rh`. Base operacional (fase 0) permanece em [RH.md](RH.md).

## Fase 1 — Operação estendida

| Módulo | Rota | Status | Observação |
|--------|------|--------|------------|
| Funcionários | `/rh/funcionarios` | ✅ Produção | Cadastro, ficha, gestor |
| Admissões / Demissões | `/rh/admissoes`, `/rh/demissoes` | ✅ Produção | Onboarding/offboarding |
| Férias | `/rh/ferias` | ✅ Produção | Fluxo completo |
| Folha operacional | `/rh/folha` | ✅ Produção | Competência simplificada |
| Portal do colaborador | `/rh/portal` | ✅ Completo | Dashboard, férias, holerites, comunicados, auto-vínculo por e-mail |
| Recrutamento | `/rh/recrutamento` | ✅ MVP | Vagas, candidatos |
| Ponto | `/rh/ponto` | ✅ MVP | Batidas web |
| Comunicação | `/rh/comunicacao` | ✅ MVP | Comunicados, fila e-mail |
| Organograma | `/rh/organograma` | ✅ MVP | Árvore por `gestor_id` |
| Auditoria | `/rh/auditoria` | ✅ MVP | `rh_audit_log` |
| Workflows | `/rh/workflows` | ✅ MVP | Templates de checklist |

**Migration:** `Version20260528140000` (tabelas roadmap + `funcionario.gestor_id`).

## Fase 2 — Folha legal e compliance

| Módulo | Rota | Status | Observação |
|--------|------|--------|------------|
| Folha legal | `/rh/folha-legal` | 🟡 Stub | Rubricas, holerite INSS/IRRF/FGTS simplificado |
| Provisões | `/rh/contabilidade` | 🟡 Stub | Cálculo percentual sobre folha |
| eSocial | `/rh/esocial` | 🟡 Stub | Lotes pendentes, sem envio real |
| Assinatura digital | `/rh/assinatura` | 🟡 Stub | Envelopes sem provedor externo |

## Fase 3 — Analytics

| Módulo | Rota | Status | Observação |
|--------|------|--------|------------|
| Analytics RH | `/rh/analytics` | 🟡 MVP | Painel de contadores por módulo |

## Arquitetura

- **Serviços:** `src/Service/Rh/*`
- **Controllers:** `src/Controller/Module/Rh/Rh*Controller.php`
- **Catálogo:** `src/Rh/RhModuleCatalog.php`
- **Hub:** `RhHubService::dashboard()` expõe `hub_modules` via `RhModuleStatsService`
- **Permissões:** `product_rh` + grants em `PermissionService` e `ProductGrantRouteMap`

## Próximos passos sugeridos

1. Integração real de e-mail (`RhEmailEvent` → Mailer).
2. Provedor de assinatura (Clicksign/D4Sign/etc.).
3. Tabelas e eventos eSocial conforme layout oficial.
4. Motor de folha legal (tabelas INSS/IRRF vigentes).
5. Conversão candidato → onboarding em `RhRecrutamentoService::convertToOnboarding`.
6. Testes automatizados dos novos serviços.
