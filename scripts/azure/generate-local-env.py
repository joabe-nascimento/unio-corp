#!/usr/bin/env python3
"""Gera scripts/azure/azure-deploy.local.env a partir dos secrets de producao (nao commitar)."""
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "azure" / "azure-deploy.local.env"
KEY = Path.home() / ".ssh" / "unio_deploy"

remote_py = r'''
import json
from pathlib import Path

def read_env(path):
    p = Path(path)
    if not p.exists():
        return {}
    out = {}
    for line in p.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        out[k.strip()] = v.strip()
    return out

j = read_env("/home2/joabef36/jurisflow-ai/.env")
s = read_env("/home2/joabef36/unio-uniojuridico/.env.local")
secret = s.get("LEGAL_AI_INTERNAL_SECRET") or j.get("LEGAL_API_SECRET", "")
print(json.dumps({
    "AZURE_OPENAI_KEY": j.get("AZURE_OPENAI_KEY", ""),
    "LEGAL_API_SECRET": secret,
}))
'''

cmd = [
    "ssh", "-p", "2222", "-i", str(KEY),
    "-o", "BatchMode=yes", "-o", "ConnectTimeout=20",
    "joabef36@br1136.hostgator.com.br",
    f"python3 -c {remote_py!r}",
]
# simpler: run heredoc via bash on server
cmd = [
    "ssh", "-p", "2222", "-i", str(KEY),
    "joabef36@br1136.hostgator.com.br",
    "python3 -",
]
proc = subprocess.run(cmd, input=remote_py.encode(), capture_output=True, check=True)
import json
data = json.loads(proc.stdout.decode().strip())

content = f"""# Gerado localmente — NAO COMMITAR
JURISFLOW_DIR=C:\\projetos\\projeto-unef\\Nova pasta\\JurisFlow-ai-service

AZURE_SUBSCRIPTION_ID=
AZURE_RESOURCE_GROUP=unio-jurisflow-rg
AZURE_LOCATION=brazilsouth
AZURE_ACR_NAME=uniojurisflowacr
AZURE_CONTAINERAPPS_ENV=jurisflow-env
AZURE_CONTAINERAPP_NAME=jurisflow-ai

LLM_PROVIDER=azure
AZURE_OPENAI_KEY={data['AZURE_OPENAI_KEY']}
AZURE_OPENAI_ENDPOINT=https://uniojuridico-openai.openai.azure.com/
AZURE_DEPLOYMENT_NAME=gpt-5-mini
LEGAL_API_SECRET={data['LEGAL_API_SECRET']}
SYMFONY_BASE_URL=https://uniojuridico.uniowork.com.br
"""
OUT.write_text(content, encoding="utf-8")
print(f"OK: {OUT}")
