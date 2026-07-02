# Unio — Plataforma de Gestão de Pessoas

Plataforma SaaS completa de RH, Gestão de Pessoas, Hub de Talentos e Hub de Maturidade.

## Stack

- **PHP 8.2** + **Symfony 7.4**
- **Doctrine ORM** + **MySQL**
- **AdminLTE 3** + **Bootstrap 4** (assets locais em `public/vendor/`, sincronizados via npm)
- **Twig** (templates)

## Perfis de Acesso

| Perfil | Acesso |
|--------|--------|
| **Tenant** | Visão completa: todas as empresas, configurações globais |
| **Admin** | Gestão de usuários, configurações da empresa |
| **Gestor** | Hub de Talentos, Hub de Maturidade, Gestão de Pessoas |
| **Supervisor** | RH, funcionários, equipes |

## Módulos

- **RH** — Funcionários, admissões, demissões, férias, folha de pagamento  
  → Documentação completa: **[docs/RH.md](docs/RH.md)** (implementado, pendências e roadmap)
- **Gestão de Pessoas** — Equipes, cargos, organograma, avaliação de desempenho
- **Hub de Talentos** — Banco de talentos, vagas, trilhas de carreira, mentoria
- **Hub de Maturidade** — Avaliação, radar por dimensão, plano de ação, histórico

## Documentação para desenvolvedores

| Documento | Conteúdo |
|-----------|----------|
| [docs/ROADMAP_90_DIAS.md](docs/ROADMAP_90_DIAS.md) | Roadmap 30/60/90 dias + backlog por módulo |
| [docs/ESTRUTURA.md](docs/ESTRUTURA.md) | Pastas, convenções, módulos |
| [docs/QUALIDADE_PERFORMANCE_E_HUBS.md](docs/QUALIDADE_PERFORMANCE_E_HUBS.md) | Empty states, hubs planejados, performance, Redis, PHPStan |
| [docs/RH.md](docs/RH.md) | Módulo RH (implementado e roadmap) |
| [docs/HUB_POS_OPERATORIO_INTEGRACAO.md](docs/HUB_POS_OPERATORIO_INTEGRACAO.md) | Hub Pós-Operatório — integração na plataforma ([PDF](docs/HUB_POS_OPERATORIO_INTEGRACAO.pdf)) |
| [docs/DEPLOY_HOSTGATOR.md](docs/DEPLOY_HOSTGATOR.md) | Deploy na HostGator (`uniowork.com.br` / `.online`) |

## Instalação

```bash
# 1. Instalar dependências
composer install
# Windows: se "composer" falhar, use php composer.phar install

# 2. Configurar banco
cp .env.local.example .env.local
# edite o .env.local com suas credenciais

# 3. Criar banco e executar migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 4. Usuários e grants de demonstração
php bin/console app:seed-users
php bin/console app:seed-product-grants

# 5. Sincronizar assets de UI (Bootstrap, AdminLTE, Font Awesome, etc.)
npm install
npm run vendor:sync

# 6. Iniciar servidor
symfony server:start

# 7. (Opcional) Qualidade e CSS de produção
composer phpstan
composer minify-css

# 8. (Opcional) Regenerar PDF da doc Hub Pós-Operatório
npx puppeteer browsers install chrome   # só na primeira vez
npm run docs:pos-operatorio-pdf
```

### Serviços Docker opcionais

```bash
# Vitória AI (assistente) — serviço definido em docker-compose.yml
docker compose up -d vitoria-ai

# Mercure e Redis: configure no .env para produção (ver config/packages/prod/cache.yaml).
# Não há serviços mercure/redis no compose local neste repositório.
```

## Estrutura

```
src/
  Controller/
    SecurityController.php    # Login/Logout
    DashboardController.php   # Dashboard principal
    RhController.php          # Módulo RH
    PessoasController.php     # Gestão de Pessoas
    TalentosController.php    # Hub de Talentos
    MaturidadeController.php  # Hub de Maturidade
    AdminController.php       # Administração
  Entity/
    User.php                  # Usuário com roles
    Empresa.php               # Empresa/Tenant
    Departamento.php
    Funcionario.php
templates/
  base.html.twig              # Layout AdminLTE
  security/login.html.twig    # Tela de login
  dashboard/ rh/ pessoas/
  talentos/ maturidade/ admin/
```
