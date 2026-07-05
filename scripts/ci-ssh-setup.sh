#!/usr/bin/env bash
# Configura SSH para deploy HostGator a partir do GitHub Actions.
set -euo pipefail

original_host="${DEPLOY_HOST:?DEPLOY_HOST required}"
host="${DEPLOY_CANONICAL_HOST:-$original_host}"
user="${DEPLOY_USER:?DEPLOY_USER required}"
port="${DEPLOY_PORT:-2222}"
key_file="${DEPLOY_KEY_FILE:-$HOME/.ssh/deploy_key}"
alias_name="${DEPLOY_SSH_ALIAS:-hg-deploy}"
preflight="${DEPLOY_SSH_PREFLIGHT:-1}"

is_cloudflare_ip() {
  case "$1" in
    104.*|172.6[0-9].*|172.67.*|2606:*) return 0 ;;
    *) return 1 ;;
  esac
}

resolve_ipv4() {
  local name="$1"
  local ipv4=""
  if command -v getent >/dev/null 2>&1; then
    ipv4="$(getent ahostsv4 "$name" | awk '/STREAM/ {print $1; exit}')"
  elif command -v dig >/dev/null 2>&1; then
    ipv4="$(dig +short A "$name" | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | head -1)"
  fi
  printf '%s' "$ipv4"
}

mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"

if [[ ! -f "$key_file" ]]; then
  echo "Chave SSH ausente em ${key_file}" >&2
  exit 1
fi

chmod 600 "$key_file"
ssh-keygen -y -f "$key_file" > /dev/null || {
  echo "Chave SSH invalida — verifique DEPLOY_SSH_KEY no repositorio" >&2
  exit 1
}

connect_host="$(resolve_ipv4 "$host")"
if [[ -z "$connect_host" ]]; then
  connect_host="$host"
fi

if is_cloudflare_ip "$connect_host"; then
  fallback="${DEPLOY_CANONICAL_HOST:-br1136.hostgator.com.br}"
  if [[ "$host" != "$fallback" ]]; then
    echo "AVISO: DEPLOY_SSH_HOST (${original_host} -> ${connect_host}) aponta para CDN/Cloudflare."
    echo "Usando host SSH canonico: ${fallback}"
    host="$fallback"
    connect_host="$(resolve_ipv4 "$host")"
    if [[ -z "$connect_host" ]]; then
      connect_host="$host"
    fi
  fi
fi

echo "SSH target: ${original_host} -> ${host} @ ${connect_host}:${port} (IPv4)"

if is_cloudflare_ip "$connect_host"; then
  echo "ERRO: host SSH ainda resolve para CDN/Cloudflare (${connect_host})." >&2
  echo "Atualize DEPLOY_SSH_HOST ou defina a variable DEPLOY_SSH_CANONICAL_HOST (ex.: br1136.hostgator.com.br)." >&2
  exit 1
fi

touch "$HOME/.ssh/known_hosts"
chmod 600 "$HOME/.ssh/known_hosts"
if command -v ssh-keyscan >/dev/null 2>&1; then
  ssh-keyscan -4 -T 10 -p "$port" "$connect_host" >> "$HOME/.ssh/known_hosts" 2>/dev/null \
    || ssh-keyscan -T 10 -p "$port" "$connect_host" >> "$HOME/.ssh/known_hosts" 2>/dev/null \
    || true
fi

{
  echo "Host ${alias_name}"
  echo "  HostName ${connect_host}"
  echo "  User ${user}"
  echo "  Port ${port}"
  echo "  IdentityFile ${key_file}"
  echo "  AddressFamily inet"
  echo "  StrictHostKeyChecking accept-new"
  echo "  BatchMode yes"
  echo "  ConnectTimeout 20"
  echo "  ServerAliveInterval 15"
  echo "  ServerAliveCountMax 3"
} >> "$HOME/.ssh/config"
chmod 600 "$HOME/.ssh/config"

if [[ "$preflight" == "1" ]]; then
  echo "Testando conectividade SSH..."
  bash "$(dirname "$0")/ci-retry.sh" ssh "${alias_name}" "echo deploy-ssh-ok"
fi
