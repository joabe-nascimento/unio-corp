"""Sincroniza assets/logotipo.svg para public/images/logos/logotipo.svg."""
from __future__ import annotations

import shutil
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "assets" / "logotipo.svg"
OUT = ROOT / "public" / "images" / "logos" / "logotipo.svg"


def main() -> int:
    if not SRC.is_file():
        print(f"Arquivo não encontrado: {SRC}", file=sys.stderr)
        return 1

    OUT.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(SRC, OUT)
    print(f"Sincronizado: {SRC.relative_to(ROOT)} → {OUT.relative_to(ROOT)} ({OUT.stat().st_size:,} bytes)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
