#!/usr/bin/env bash
# Garante vars UNIO_ORGANISMO_* por ambiente.
# Clinica: defaults em config/packages/unio_organismo.yaml
# Production uniowork.com.br: perfil Unio Studio via .env.local (valores sempre entre aspas)

organismo_env_set_quoted() {
  local env_file="$1"
  local key="$2"
  local value="$3"
  local escaped="${value//\\/\\\\}"
  escaped="${escaped//\"/\\\"}"

  if grep -q "^${key}=" "$env_file" 2>/dev/null; then
    sed -i "/^${key}=/d" "$env_file"
  fi

  printf '%s="%s"\n' "$key" "$escaped" >> "$env_file"
}

organismo_env_ensure() {
  local env_file="$1"
  local key="$2"
  local value="$3"

  if grep -q "^${key}=" "$env_file" 2>/dev/null; then
    return 0
  fi

  organismo_env_set_quoted "$env_file" "$key" "$value"
}

organismo_env_sync_for_uri() {
  local env_file="${1:-}"
  local default_uri="${2:-}"

  [[ -f "$env_file" ]] || return 0
  [[ -n "$default_uri" ]] || return 0

  case "$default_uri" in
    https://uniowork.com.br|http://uniowork.com.br)
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_ENABLED true
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_HOME true
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_BRAND_NAME 'Unio'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_BRAND_SLOGAN 'Plataforma que evolui com você.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_TITLE 'Sites, sistemas e produtos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_TITLE_ACCENT 'em um só organismo.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_DESC 'Unio reúne hubs modulares para saúde, educação e operações corporativas — com a mesma identidade visual refinada, portal do cliente e assistente Lumen.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_LUMEN_SUBTITLE 'Assistente inteligente'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL 'Projeto'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL_ARTIGO 'do projeto'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL_PLURAL 'Projetos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_MATURIDADE 'Painel de entregas'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SECTION_CLIENTS 'Projetos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PACIENTES 'Clientes'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SALA_CRITICA 'Prioridade alta'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_ALERTAS 'Alertas'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SECTION_DELIVERABLES 'Entregas'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PROTOCOLOS 'Playbooks'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_QUESTIONARIOS 'Check-ins'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PORTAL 'Portal do cliente'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_PROJECTS_ACTIVE 'Projetos ativos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_IN_PROGRESS 'Em andamento'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_CASES_HEADING 'Entregas em andamento'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_KPIS_HEADING 'Sinais do organismo'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_EMPTY 'Nenhuma entrega ativa — tudo em dia.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HUB_HERO_TITLE 'Painel de projetos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_MARKETING_EYEBROW 'Plataforma digital modular'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_MARKETING_TAGLINE 'Plataforma que evolui com você.'
      echo "Organismo: perfil Unio (central) sincronizado em $env_file"
      ;;
    https://uniosaude.uniowork.com.br|http://uniosaude.uniowork.com.br)
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_ENABLED true
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_HOME true
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_BRAND_NAME 'Unio Saúde'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_BRAND_SLOGAN 'Saúde que acompanha.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_TITLE 'Gestão clínica integrada'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_TITLE_ACCENT 'modular e humana.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_DESC 'Unio Saúde reúne pós-operatório, carteirinha digital e guia médico. Ative só os produtos que sua clínica precisa.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_LUMEN_SUBTITLE 'Assistente clínico'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL 'Clínica'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL_ARTIGO 'da clínica'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL_PLURAL 'Clínicas'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_MATURIDADE 'Painel de Recuperação'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SECTION_CLIENTS 'Pacientes'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PACIENTES 'Pacientes'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SALA_CRITICA 'Sala Crítica'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_ALERTAS 'Alertas'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SECTION_DELIVERABLES 'Acompanhamento'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PROTOCOLOS 'Protocolos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_QUESTIONARIOS 'Questionários'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PORTAL 'Portal do Paciente'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_PROJECTS_ACTIVE 'Pacientes ativos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_IN_PROGRESS 'Em acompanhamento'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_CASES_HEADING 'Casos em andamento'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_KPIS_HEADING 'Sinais vitais da clínica'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_EMPTY 'Nenhum caso ativo — tudo tranquilo na clínica.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HUB_HERO_TITLE 'Painel clínico'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_MARKETING_EYEBROW 'Plataforma clínica modular'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_MARKETING_TAGLINE 'Saúde que acompanha.'
      echo "Organismo: perfil Unio Saúde sincronizado em $env_file"
      ;;
    https://clinicaunio.uniowork.com.br|http://clinicaunio.uniowork.com.br)
      organismo_env_ensure "$env_file" UNIO_ORGANISMO_ENABLED true
      organismo_env_ensure "$env_file" UNIO_ORGANISMO_PULSO_HOME true
      echo "Organismo: clinicaunio (legado) — defaults YAML da clínica"
      ;;
  esac
}
