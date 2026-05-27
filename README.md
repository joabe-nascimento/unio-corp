# Huplex — Plataforma de Gestão de Pessoas

Plataforma SaaS completa de RH, Gestão de Pessoas, Hub de Talentos e Hub de Maturidade.

## Stack

- **PHP 8.2** + **Symfony 7.4**
- **Doctrine ORM** + **MySQL**
- **AdminLTE 3** (UI via CDN)
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

## Instalação

```bash
# 1. Instalar dependências
composer install

# 2. Configurar banco
cp .env.local.example .env.local
# edite o .env.local com suas credenciais

# 3. Criar banco e executar migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 4. Criar usuário admin inicial
php bin/console doctrine:fixtures:load  # (quando disponível)

# 5. Iniciar servidor
symfony server:start
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
