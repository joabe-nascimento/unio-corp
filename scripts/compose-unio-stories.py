"""Compose official Unio Saúde logo + app UI onto lifestyle phone photos."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "assets"
CURSOR_ASSETS = Path(r"C:\Users\joabe\.cursor\projects\c-projetos-Nova-pasta-unio-corp\assets")
OUT_DIR = ROOT / "public" / "images" / "marketing"
OUT_DIR.mkdir(parents=True, exist_ok=True)

LOGO_SRC = ASSETS / "unio-saude.png"
W, H = 1080, 1920


def font(size: int, bold: bool = True):
    paths = [
        r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf",
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
    ]
    for p in paths:
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def fit(im: Image.Image, max_w: int, max_h: int) -> Image.Image:
    out = im.copy()
    out.thumbnail((max_w, max_h), Image.Resampling.LANCZOS)
    return out


def make_phone_ui(logo: Image.Image, width: int = 420, height: int = 860) -> Image.Image:
    ui = Image.new("RGBA", (width, height), (248, 251, 255, 255))
    d = ImageDraw.Draw(ui)

    d.rectangle((0, 0, width, 34), fill=(15, 36, 58, 255))
    d.text((14, 7), "09:41", font=font(15, False), fill=(255, 255, 255, 235))

    d.rectangle((0, 34, width, 128), fill=(255, 255, 255, 255))
    mark = fit(logo, width - 70, 72)
    ui.alpha_composite(mark, ((width - mark.width) // 2, 42))

    d.rectangle((0, 128, width, 166), fill=(234, 240, 251, 255))
    d.text((18, 138), "Saúde que acompanha.", font=font(17, True), fill=(61, 97, 168, 255))

    d.rounded_rectangle((16, 184, width - 16, 310), 16, fill=(255, 255, 255, 255), outline=(210, 222, 240, 255))
    d.text((30, 200), "Pulso da clínica", font=font(20, True), fill=(30, 42, 58, 255))
    d.text((30, 232), "Saudável · 92", font=font(32, True), fill=(17, 158, 249, 255))
    d.text((30, 276), "3 pacientes ativos · 0 P1", font=font(16, False), fill=(100, 116, 139, 255))

    d.text((22, 336), "Trilha Unio", font=font(20, True), fill=(30, 42, 58, 255))
    steps = [("D+1", True), ("D+7", True), ("D+14", False), ("Alta", False)]
    x0 = 26
    for i, (step, done) in enumerate(steps):
        cx = x0 + i * 95
        color = (16, 185, 129, 255) if done else ((75, 114, 190, 255) if i == 2 else (148, 163, 184, 255))
        d.ellipse((cx, 372, cx + 16, 388), fill=color)
        d.text((cx - 6, 398), step, font=font(15, True), fill=(71, 85, 105, 255))
        if i < len(steps) - 1:
            d.rectangle((cx + 20, 378, cx + 86, 382), fill=(203, 213, 225, 255))

    d.rounded_rectangle((16, 450, width - 16, 620), 16, fill=(255, 255, 255, 255), outline=(210, 222, 240, 255))
    d.text((30, 470), "Marina Alves", font=font(22, True), fill=(30, 42, 58, 255))
    d.text((30, 504), "Paciente · D+7 na Trilha", font=font(16, False), fill=(100, 116, 139, 255))
    d.rounded_rectangle((30, 545, 210, 588), 14, fill=(75, 114, 190, 255))
    d.text((50, 554), "Abrir ficha", font=font(17, True), fill=(255, 255, 255, 255))

    d.rectangle((0, height - 72, width, height), fill=(255, 255, 255, 255))
    d.line((0, height - 72, width, height - 72), fill=(226, 232, 240, 255), width=2)
    for label, x, active in (("Início", 36, False), ("Pulso", 140, True), ("Pacientes", 235, False), ("Mais", 345, False)):
        d.text((x, height - 44), label, font=font(14, True), fill=(75, 114, 190, 255) if active else (148, 163, 184, 255))

    return ui


def rounded_mask(size: tuple[int, int], radius: int) -> Image.Image:
    mask = Image.new("L", size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, size[0] - 1, size[1] - 1), radius, fill=255)
    return mask


def paste_ui(canvas: Image.Image, ui: Image.Image, box: tuple[int, int, int, int], radius: int = 48) -> None:
    x0, y0, x1, y1 = box
    target = ui.resize((x1 - x0, y1 - y0), Image.Resampling.LANCZOS)
    canvas.paste(target, (x0, y0), rounded_mask(target.size, radius))


def add_story_copy(canvas: Image.Image, lines: list[str], cta: str) -> None:
    """Bottom glass panel — works on bright lifestyle photos."""
    panel = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(panel)
    d.rounded_rectangle((28, 1460, W - 28, 1880), 28, fill=(8, 24, 42, 210))

    logo = fit(Image.open(LOGO_SRC).convert("RGBA"), 280, 130)
    panel.alpha_composite(logo, (56, 1490))

    y = 1490 + logo.height + 12
    for line in lines:
        d.text((56, y), line, font=font(36, True), fill=(255, 255, 255, 255))
        y += 46
    d.text((56, y + 4), "Pulso · Trilha Unio · Sala Crítica", font=font(20, False), fill=(170, 210, 235, 255))

    d.rounded_rectangle((56, 1788, 420, 1850), 30, fill=(255, 255, 255, 245))
    tw = d.textlength(cta, font=font(24, True))
    d.text((56 + (364 - tw) / 2, 1804), cta, font=font(24, True), fill=(15, 36, 58, 255))

    canvas.alpha_composite(panel)


def compose(bg_name: str, out_name: str, phone_box: tuple[int, int, int, int], lines: list[str], cta: str) -> Path:
    bg = Image.open(CURSOR_ASSETS / bg_name).convert("RGBA").resize((W, H), Image.Resampling.LANCZOS)
    logo = Image.open(LOGO_SRC).convert("RGBA")
    ui = make_phone_ui(logo)
    paste_ui(bg, ui, phone_box, radius=52)
    add_story_copy(bg, lines, cta)

    out = CURSOR_ASSETS / out_name
    rgb = bg.convert("RGB")
    rgb.save(out, "PNG")
    rgb.save(OUT_DIR / out_name, "PNG")
    print("OK", out)
    return out


def main() -> None:
    # Tuned phone-screen boxes (1080x1920)
    compose(
        "unio-person-phone-1-bg.png",
        "unio-story-pessoa-app-1.png",
        phone_box=(470, 560, 900, 1480),
        lines=["O pós-op na palma", "da mão da clínica."],
        cta="Conhecer a Unio →",
    )
    compose(
        "unio-person-phone-2-bg.png",
        "unio-story-pessoa-app-2.png",
        phone_box=(400, 700, 780, 1520),
        lines=["Trilha Unio aberta.", "Cuidado que acompanha."],
        cta="Saiba mais →",
    )


if __name__ == "__main__":
    main()
