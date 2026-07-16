"""Uma story Unio bem acabada: mulher + celular + logo oficial + textos."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageFilter, ImageEnhance

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "assets"
CURSOR = Path(r"C:\Users\joabe\.cursor\projects\c-projetos-Nova-pasta-unio-corp\assets")
OUT = ROOT / "public" / "images" / "marketing"
OUT.mkdir(parents=True, exist_ok=True)

LOGO = ASSETS / "unio-saude.png"
W, H = 1080, 1920


def font(size: int, bold: bool = True):
    for p in (
        r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf",
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
    ):
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def fit(im: Image.Image, mw: int, mh: int) -> Image.Image:
    o = im.copy()
    o.thumbnail((mw, mh), Image.Resampling.LANCZOS)
    return o


def make_screen(logo: Image.Image, w: int, h: int) -> Image.Image:
    """Tela Unio limpa e legível para o celular."""
    ui = Image.new("RGBA", (w, h), (247, 250, 255, 255))
    d = ImageDraw.Draw(ui)

    # status
    d.rectangle((0, 0, w, max(28, h // 28)), fill=(15, 36, 58, 255))
    d.text((12, 6), "09:41", font=font(max(12, w // 28), False), fill=(255, 255, 255, 235))

    # header logo
    head_h = int(h * 0.14)
    d.rectangle((0, max(28, h // 28), w, head_h), fill=(255, 255, 255, 255))
    mark = fit(logo, int(w * 0.72), int(head_h * 0.62))
    ui.alpha_composite(mark, ((w - mark.width) // 2, max(28, h // 28) + (head_h - max(28, h // 28) - mark.height) // 2))

    # slogan strip
    y = head_h
    strip_h = int(h * 0.045)
    d.rectangle((0, y, w, y + strip_h), fill=(234, 240, 251, 255))
    d.text((16, y + strip_h // 4), "Saúde que acompanha.", font=font(max(13, w // 24), True), fill=(61, 97, 168, 255))
    y += strip_h + int(h * 0.02)

    pad = int(w * 0.045)
    # Pulso card
    card_h = int(h * 0.16)
    d.rounded_rectangle((pad, y, w - pad, y + card_h), 16, fill=(255, 255, 255, 255), outline=(214, 224, 240, 255), width=2)
    d.text((pad + 16, y + 14), "Pulso da clínica", font=font(max(14, w // 22), True), fill=(30, 42, 58, 255))
    d.text((pad + 16, y + int(card_h * 0.38)), "Saudável · 92", font=font(max(22, w // 14), True), fill=(17, 158, 249, 255))
    d.text((pad + 16, y + int(card_h * 0.72)), "3 pacientes ativos · 0 P1", font=font(max(12, w // 28), False), fill=(100, 116, 139, 255))
    y += card_h + int(h * 0.025)

    # Trilha
    d.text((pad, y), "Trilha Unio", font=font(max(14, w // 22), True), fill=(30, 42, 58, 255))
    y += int(h * 0.035)
    steps = [("D+1", True), ("D+7", True), ("D+14", False), ("Alta", False)]
    usable = w - 2 * pad
    gap = usable / (len(steps) - 1)
    for i, (lab, done) in enumerate(steps):
        cx = int(pad + i * gap)
        col = (16, 185, 129, 255) if done else ((75, 114, 190, 255) if i == 2 else (148, 163, 184, 255))
        r = 8
        d.ellipse((cx - r, y, cx + r, y + 2 * r), fill=col)
        tw = d.textlength(lab, font=font(max(11, w // 30), True))
        d.text((cx - tw / 2, y + 22), lab, font=font(max(11, w // 30), True), fill=(71, 85, 105, 255))
        if i < len(steps) - 1:
            d.rectangle((cx + r + 4, y + r - 1, int(pad + (i + 1) * gap) - r - 4, y + r + 2), fill=(203, 213, 225, 255))
    y += int(h * 0.08)

    # Patient
    card_h = int(h * 0.18)
    d.rounded_rectangle((pad, y, w - pad, y + card_h), 16, fill=(255, 255, 255, 255), outline=(214, 224, 240, 255), width=2)
    d.text((pad + 16, y + 16), "Marina Alves", font=font(max(16, w // 18), True), fill=(30, 42, 58, 255))
    d.text((pad + 16, y + int(card_h * 0.38)), "Paciente · D+7 na Trilha", font=font(max(12, w // 26), False), fill=(100, 116, 139, 255))
    btn_y = y + int(card_h * 0.62)
    d.rounded_rectangle((pad + 16, btn_y, pad + int(w * 0.45), btn_y + int(card_h * 0.28)), 12, fill=(75, 114, 190, 255))
    d.text((pad + 36, btn_y + 8), "Abrir ficha", font=font(max(13, w // 24), True), fill=(255, 255, 255, 255))

    # nav
    nav_h = int(h * 0.085)
    d.rectangle((0, h - nav_h, w, h), fill=(255, 255, 255, 255))
    d.line((0, h - nav_h, w, h - nav_h), fill=(226, 232, 240, 255), width=2)
    for lab, x_frac, on in (("Início", 0.08, False), ("Pulso", 0.32, True), ("Pacientes", 0.55, False), ("Mais", 0.82, False)):
        d.text((int(w * x_frac), h - nav_h + nav_h // 3), lab, font=font(max(11, w // 30), True), fill=(75, 114, 190, 255) if on else (148, 163, 184, 255))

    return ui


def rounded_paste(base: Image.Image, overlay: Image.Image, box: tuple[int, int, int, int], radius: int) -> None:
    x0, y0, x1, y1 = box
    target = overlay.resize((x1 - x0, y1 - y0), Image.Resampling.LANCZOS)
    mask = Image.new("L", target.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, target.width - 1, target.height - 1), radius, fill=255)
    # soft shadow behind screen
    shadow = Image.new("RGBA", base.size, (0, 0, 0, 0))
    sd = ImageDraw.Draw(shadow)
    sd.rounded_rectangle((x0 + 4, y0 + 8, x1 + 4, y1 + 8), radius, fill=(0, 0, 0, 55))
    shadow = shadow.filter(ImageFilter.GaussianBlur(8))
    base.alpha_composite(shadow)
    base.paste(target, (x0, y0), mask)


def wrap(draw: ImageDraw.ImageDraw, text: str, f, max_w: int) -> list[str]:
    words = text.split()
    lines, cur = [], ""
    for w in words:
        test = (cur + " " + w).strip()
        if draw.textlength(test, font=f) <= max_w:
            cur = test
        else:
            if cur:
                lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def build() -> Path:
    src = CURSOR / "unio-woman-blank-phone.png"
    canvas = Image.open(src).convert("RGBA").resize((W, H), Image.Resampling.LANCZOS)

    # slight contrast polish
    canvas = ImageEnhance.Contrast(canvas).enhance(1.05)
    canvas = ImageEnhance.Color(canvas).enhance(1.05)

    # left readability wash (subtle teal, premium — not a hard cut)
    wash = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    wd = ImageDraw.Draw(wash)
    for i in range(480):
        t = 1 - i / 480
        a = int(125 * (t ** 1.25))
        wd.line([(i, 0), (i, H)], fill=(5, 32, 48, a))
    # soft vignette corners
    for i in range(0, 220):
        a = int(70 * (1 - i / 220))
        wd.line([(0, i), (W, i)], fill=(4, 20, 32, a // 2))
    canvas = Image.alpha_composite(canvas, wash)

    logo = Image.open(LOGO).convert("RGBA")

    # phone screen — inset inside blank white phone glass
    phone_box = (360, 800, 712, 1520)
    screen = make_screen(logo, 352, 720)
    rounded_paste(canvas, screen, phone_box, radius=40)

    d = ImageDraw.Draw(canvas)

    # top logo official
    top_logo = fit(logo, 340, 150)
    canvas.alpha_composite(top_logo, (44, 56))

    y = 56 + top_logo.height + 22
    # badge
    badge = "Atenção!"
    bf = font(26, True)
    bw = int(d.textlength(badge, font=bf)) + 44
    d.rounded_rectangle((44, y, 44 + bw, y + 50), 25, fill=(255, 255, 255, 250))
    d.text((44 + 22, y + 10), badge, font=bf, fill=(12, 36, 58, 255))
    y += 74

    # headlines
    line1 = "A cirurgia termina."
    line2 = "O cuidado, não."
    f1 = font(42, True)
    f2 = font(46, True)
    for line, f in ((line1, f1), (line2, f2)):
        for wl in wrap(d, line, f, 500):
            # subtle text shadow
            d.text((46, y + 2), wl, font=f, fill=(0, 0, 0, 90))
            d.text((44, y), wl, font=f, fill=(255, 255, 255, 255))
            y += 56
    y += 8
    sub = "Pulso · Trilha Unio · Sala Crítica"
    d.text((44, y), sub, font=font(22, False), fill=(186, 230, 253, 255))
    y += 56

    # CTA
    cta = "Saiba mais!"
    cf = font(26, True)
    cw = int(d.textlength(cta, font=cf)) + 52
    d.rounded_rectangle((44, y, 44 + cw, y + 58), 29, fill=(255, 255, 255, 250))
    d.text((44 + 26, y + 14), cta, font=cf, fill=(12, 36, 58, 255))

    out_name = "unio-story-mulher-final.png"
    rgb = canvas.convert("RGB")
    out1 = CURSOR / out_name
    out2 = OUT / out_name
    rgb.save(out1, "PNG", optimize=True)
    rgb.save(out2, "PNG", optimize=True)
    print("OK", out1)
    print("OK", out2)
    return out1


if __name__ == "__main__":
    build()
