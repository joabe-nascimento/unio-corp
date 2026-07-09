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
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_BRAND_NAME 'Unio Studio'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_BRAND_SLOGAN 'Sites, sistemas e projetos que evoluem.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_TITLE 'Desenvolvimento de sites, apps'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_TITLE_ACCENT 'e projetos digitais.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HERO_DESC 'Unio Studio reúne gestão de projetos, entregas, playbooks de implementação e portal do cliente — um ambiente pensado para equipes que constroem sites, sistemas e projetos clínicos com clareza e ritmo.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_LUMEN_SUBTITLE 'Assistente do studio'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL 'Projeto'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL_ARTIGO 'do projeto'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_UNIT_LABEL_PLURAL 'Projetos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_MATURIDADE 'Painel de entregas'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SECTION_CLIENTS 'Projetos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PACIENTES 'Projetos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SALA_CRITICA 'Prioridade alta'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_ALERTAS 'Alertas'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_SECTION_DELIVERABLES 'Entregas'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PROTOCOLOS 'Playbooks'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_QUESTIONARIOS 'Check-ins'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_NAV_PORTAL 'Portal do cliente'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_PROJECTS_ACTIVE 'Projetos ativos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_IN_PROGRESS 'Em andamento'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_CASES_HEADING 'Entregas em andamento'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_KPIS_HEADING 'Sinais do studio'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_PULSO_EMPTY 'Nenhuma entrega ativa — tudo em dia.'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_HUB_HERO_TITLE 'Painel de projetos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_MARKETING_EYEBROW 'Studio digital & projetos clínicos'
      organismo_env_set_quoted "$env_file" UNIO_ORGANISMO_MARKETING_TAGLINE 'Sites, sistemas e projetos que evoluem.'
      echo "Organismo: perfil Studio sincronizado em $env_file"
      ;;
    https://clinicaunio.uniowork.com.br|http://clinicaunio.uniowork.com.br)
      organismo_env_ensure "$env_file" UNIO_ORGANISMO_ENABLED true
      organismo_env_ensure "$env_file" UNIO_ORGANISMO_PULSO_HOME true
      echo "Organismo: clinicaunio — defaults YAML da clínica (sem sobrescrever marca)"
      ;;
  esac
}
