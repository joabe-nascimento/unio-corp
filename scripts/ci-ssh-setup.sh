#!/usr/bin/env bash
# Configura SSH para deploy HostGator a partir do GitHub Actions.
set -euo pipefail

host="${DEPLOY_HOST:?DEPLOY_HOST required}"
user="${DEPLOY_USER:?DEPLOY_USER required}"
port="${DEPLOY_PORT:-2222}"
key_file="${DEPLOY_KEY_FILE:-$HOME/.ssh/deploy_key}"
alias_name="${DEPLOY_SSH_ALIAS:-hg-deploy}"
preflight="${DEPLOY_SSH_PREFLIGHT:-1}"

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

connect_host="$host"
if command -v getent >/dev/null 2>&1; then
  ipv4="$(getent ahostsv4 "$host" | awk '/STREAM/ {print $1; exit}')"
  if [[ -n "${ipv4:-}" ]]; then
    connect_host="$ipv4"
  fi
elif command -v dig >/dev/null 2>&1; then
  ipv4="$(dig +short A "$host" | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | head -1)"
  if [[ -n "${ipv4:-}" ]]; then
    connect_host="$ipv4"
  fi
fi

echo "SSH target: ${host} -> ${connect_host}:${port} (IPv4)"

case "$connect_host" in
  104.*|172.6[0-9].*|172.67.*|2606:*)
    echo "ERRO: DEPLOY_SSH_HOST resolve para CDN/Cloudflare (${connect_host})." >&2
    echo "Use o hostname gatorXXXX.hostgator.com.br ou o IP do servidor no cPanel — nao o dominio do site." >&2
    exit 1
    ;;
esac

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
  "$(dirname "$0")/ci-retry.sh" ssh "${alias_name}" "echo deploy-ssh-ok"
fi
