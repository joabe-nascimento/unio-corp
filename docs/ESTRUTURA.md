# Estrutura do projeto Unio

Organização pensada para manutenção por módulo (RH, Pessoas) e por hub (Talentos, Maturidade).

## Backend (`src/`)

```
src/
├── Controller/
│   ├── Core/              # Rotas globais (dashboard, workspace)
│   │   ├── DashboardController.php
│   │   └── WorkspaceController.php
│   ├── Auth/
│   │   └── SecurityController.php
│   └── Module/            # Um namespace por módulo/hub
│       ├── Operacoes/     # Hub RH + Pessoas
│       ├── Rh/
│       ├── Pessoas/
│       ├── Talentos/      # Hub
│       ├── Maturidade/    # Hub
│       ├── Engenharia/    # Hub — obras e conformidade
│       ├── Publicidade/   # Hub — campanhas e mídia
│       └── Admin/
├── Entity/
├── Repository/
├── Service/
├── EventSubscriber/
└── Command/
```

**Convenção:** cada controller de módulo define `private const T = 'modules/{nome}/';` e renderiza templates nesse prefixo.

## Frontend (`templates/`)

```
templates/
├── base.html.twig         # Layout principal + CSS global
├── components/            # UI reutilizável
│   ├── stat_card.html.twig
│   ├── empty_state.html.twig
│   ├── page_banner.html.twig
│   ├── module_tile.html.twig
│   └── page_stub.html.twig
├── macros/
│   └── empresa.html.twig
├── core/
│   └── dashboard/
├── auth/
│   └── login.html.twig
├── workspace/
│   └── select.html.twig
└── modules/
    ├── rh/
    ├── pessoas/
    ├── talentos/        # Hub
    ├── maturidade/      # Hub
    ├── engenharia/      # Módulo — dentro do Hub Operações
    ├── publicidade/     # Módulo — dentro do Hub de Maturidade
    └── admin/
```

## Componentes

| Componente | Uso |
|------------|-----|
| `stat_card` | Métricas com altura uniforme (`.stats-row`) |
| `empty_state` | Listas vazias / sem dados |
| `page_banner` | Cabeçalho de contexto (perfil + logo da empresa) |
| `module_tile` | Atalhos no dashboard |
| `page_stub` | Páginas ainda em desenvolvimento |

## Assets públicos

```
public/
├── images/logos/        # Logos das empresas (campo Empresa.logo)
└── index.php
```

## Menu lateral (por perfil)

O menu usa o **perfil principal** do usuário (`User.perfil`), não a hierarquia Symfony (evita admin ver RH/Talentos).

| Perfil | Dashboard | Operações | Talentos | Maturidade | Plataforma |
|--------|-----------|-----------|----------|------------|------------|
| MEMBRO | sim | — | — | — | — |
| SUPERVISOR_* | sim | sim (RH + Pessoas) | — | — | — |
| GESTOR_* | sim | sim (+ Obras) | sim | sim (+ Marca) | — |
| **TENANT** | sim (layout tenant) | sim | sim | sim | sim |

\* Supervisor vê apenas RH e Gestão de Pessoas dentro do Hub Operações.

### Módulos dentro dos hubs (gestor+)

**Hub Operações** — seção **Obras e Projetos** (`/engenharia`): Projetos · Cronograma · Orçamentos · Equipes de Campo · Documentação · Normas

**Hub de Maturidade** — seção **Marca e Comunicação** (`/publicidade`): Campanhas · Clientes · Criativos · Mídia · Briefings · Métricas e ROI

- **TENANT** = operador da plataforma (acesso 100 %). Não existe mais perfil `ADMIN` (legado migra para `GESTOR` com `app:migrate-admin-perfil`).
- **Hub Operações** (`/hub/operacoes`): RH, Gestão de Pessoas e Obras e Projetos.
- **Plataforma** (`/admin`): usuários, empresas e configurações — só TENANT.
- **Layout tenant**: banner multi-empresa e stats globais.

Globais Twig: `nav_show_*`, `nav_layout`, definidos em `WorkspaceTwigSubscriber`.

### Enforço de acesso (Symfony)

O menu usa `User.perfil`; as rotas usam **roles** (`security.yaml` + `#[IsGranted]`). Hierarquia:

- `ROLE_SUPERVISOR_EQUIPE` → Hub Operações, RH, Pessoas
- `ROLE_GESTOR_EQUIPE` → + Talentos, Maturidade, Engenharia, Publicidade
- `ROLE_TENANT` → + Plataforma (`/admin`)

Controllers alinhados com a tabela acima (`RhController`, `PessoasController` = `ROLE_SUPERVISOR_EQUIPE`; `Talentos`/`Maturidade` = `ROLE_GESTOR_EQUIPE`).

## Comandos úteis

```bash
php bin/console app:patch-empresa-logos
php bin/console app:seed-users
```

## Adicionar um novo submódulo

1. Criar action em `src/Controller/Module/{Modulo}/{Modulo}Controller.php`
2. Criar `templates/modules/{modulo}/nova-pagina.html.twig`
3. Registrar rota no menu em `templates/base.html.twig`
4. Usar `page_stub` ou `empty_state` até existir dados reais
