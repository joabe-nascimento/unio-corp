# Onboarding e Offboarding — onde ficam no Unio

## Localização no produto

| Fluxo | Hub | Módulo | Menu | Rota | Grant (`product_rh`) |
|-------|-----|--------|------|------|----------------------|
| **Onboarding** (entrada) | Operações | Recursos Humanos | Admissões | `/rh/admissoes` | `admissoes` |
| **Offboarding** (saída) | Operações | Recursos Humanos | Demissões | `/rh/demissoes` | `admissoes` |

Não ficam em Talentos (recrutamento pré-contratação) nem em Gestão de Pessoas (organograma pós-admissão).

## Relação entre entidades

```
Onboarding (RhOnboardingProcess) → conclui → Funcionario (ATIVO) → opcional User (acesso)
Offboarding (RhOffboardingProcess) → conclui → Funcionario (INATIVO) + data demissão
```

- **User**: conta de login na plataforma.
- **Funcionario**: registro de RH da empresa.
- **Pessoas / Membros**: visão organizacional (integração futura após admissão).

## Branch

`feature/core-onboarding-offboarding`
