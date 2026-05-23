# Estrutura do projeto Huplex

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

| Perfil | Dashboard | Hub Operações | Hub Talentos | Hub Maturidade | Admin |
|--------|-----------|---------------|--------------|----------------|-------|
| MEMBRO | sim | — | — | — | — |
| SUPERVISOR_* | sim | sim | — | — | — |
| GESTOR_* | sim | sim | sim | sim | — |
| ADMIN / TENANT | sim (layout admin) | — | — | — | sim |

- **Hub Operações** (`/hub/operacoes`): agrupa RH e Gestão de Pessoas num único hub.
- **Layout admin**: `NavigationService::getLayout()` → `admin` (banner e stats multi-empresa).
- **Layout user**: `gestor`, `supervisor` ou `membro`.

Globais Twig: `nav_show_*`, `nav_layout`, definidos em `WorkspaceTwigSubscriber`.

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
