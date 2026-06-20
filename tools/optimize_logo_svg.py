"""Otimiza logo-completa.svg: extrai PNG, comprime e gera SVG leve com href absoluto."""
from __future__ import annotations

import base64
import re
import sys
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "assets" / "logo-completa.svg"
OUT_DIR = ROOT / "public" / "images" / "logos"
RASTER = OUT_DIR / "logo-completa-raster.png"
SVG_OUT = OUT_DIR / "logo-completa.svg"
ASSETS_OUT = ROOT / "assets" / "logo-completa.svg"
HREF = "logo-completa-raster.png"


def main() -> int:
    text = SRC.read_text(encoding="utf-8")
    if 'data:image/png;base64,' not in text:
        print("SVG já otimizado (sem base64 embutido).")
        return 0

    match = re.search(r'xlink:href="data:image/png;base64,([^"]+)"', text)
    if not match:
        print("PNG embutido não encontrado.", file=sys.stderr)
        return 1

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    RASTER.write_bytes(base64.b64decode(match.group(1)))

    slim = re.sub(
        r'<image id="image0_83_48"[^>]*xlink:href="data:image/png;base64,[^"]+"',
        f'<image id="image0_83_48" width="1536" height="1024" preserveAspectRatio="none" xlink:href="{HREF}"',
        text,
        count=1,
    )
    SVG_OUT.write_text(slim, encoding="utf-8")
    ASSETS_OUT.write_text(slim, encoding="utf-8")

    before = RASTER.stat().st_size
    img = Image.open(RASTER).convert("RGBA")
    alpha = img.getchannel("A")
    rgb = img.convert("RGB")
    quantized = rgb.quantize(colors=256, method=Image.Quantize.MEDIANCUT).convert("RGBA")
    quantized.putalpha(alpha)
    quantized.save(RASTER, format="PNG", optimize=True, compress_level=9)
    after = RASTER.stat().st_size

    print(f"SVG: {SVG_OUT.stat().st_size:,} bytes")
    print(f"PNG: {before:,} -> {after:,} bytes ({after / 1024 / 1024:.2f} MB)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
