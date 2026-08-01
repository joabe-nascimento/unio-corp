#!/usr/bin/env python3
"""Corrige LEGAL_AI_URL no .env.local do Unio Jurídico (produção)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ENV_FILE = Path(sys.argv[1] if len(sys.argv) > 1 else "/home2/joabef36/unio-uniojuridico/.env.local")
PORT = sys.argv[2] if len(sys.argv) > 2 else "8098"
TARGET = f'LEGAL_AI_URL="http://127.0.0.1:{PORT}"'

text = ENV_FILE.read_text(encoding="utf-8", errors="replace")
if re.search(r"^LEGAL_AI_URL=", text, re.M):
    text = re.sub(r"^LEGAL_AI_URL=.*$", TARGET, text, flags=re.M)
else:
    text = text.rstrip() + "\n" + TARGET + "\n"

if re.search(r"^LEGAL_AI_ENABLED=", text, re.M):
    text = re.sub(r'^LEGAL_AI_ENABLED=.*$', 'LEGAL_AI_ENABLED="true"', text, flags=re.M)
else:
    text = text.rstrip() + '\nLEGAL_AI_ENABLED="true"\n'

ENV_FILE.write_text(text, encoding="utf-8")
print(TARGET)
