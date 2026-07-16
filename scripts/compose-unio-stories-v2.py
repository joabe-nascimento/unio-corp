"""Story arts Unio Saúde — fotos com app + textos no estilo Caveo."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "assets"
CURSOR = Path(r"C:\Users\joabe\.cursor\projects\c-projetos-Nova-pasta-unio-corp\assets")
OUT = ROOT / "public" / "images" / "marketing"
OUT.mkdir(parents=True, exist_ok=True)

LOGO = ASSETS / "unio-saude.png"
W, H = 1080, 1920


def fnt(size: int, bold: bool = True):
    paths = [
        r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf",
        r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf",
    ]
    for p in paths:
        if Path(p).exists():
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()


def fit(im: Image.Image, mw: int, mh: int) -> Image.Image:
    o = im.copy()
    o.thumbnail((mw, mh), Image.Resampling.LANCZOS)
    return o


def make_ui(logo: Image.Image, w: int = 390, h: int = 820) -> Image.Image:
    ui = Image.new("RGBA", (w, h), (248, 251, 255, 255))
    d = ImageDraw.Draw(ui)
    d.rectangle((0, 0, w, 32), fill=(12, 28, 48, 255))
    d.text((12, 6), "09:41", font=fnt(14, False), fill=(255, 255, 255, 230))

    d.rectangle((0, 32, w, 118), fill=(255, 255, 255, 255))
    mark = fit(logo, w - 60, 68)
    ui.alpha_composite(mark, ((w - mark.width) // 2, 40))

    d.rectangle((0, 118, w, 154), fill=(234, 240, 251, 255))
    d.text((16, 128), "Saúde que acompanha.", font=fnt(16, True), fill=(61, 97, 168, 255))

    d.rounded_rectangle((14, 172, w - 14, 290), 14, fill=(255, 255, 255, 255), outline=(210, 222, 240, 255))
    d.text((28, 186), "Pulso da clínica", font=fnt(18, True), fill=(30, 42, 58, 255))
    d.text((28, 216), "Saudável · 92", font=fnt(30, True), fill=(17, 158, 249, 255))
    d.text((28, 256), "3 pacientes · 0 P1", font=fnt(15, False), fill=(100, 116, 139, 255))

    d.text((20, 312), "Trilha Unio", font=fnt(18, True), fill=(30, 42, 58, 255))
    steps = [("D+1", True), ("D+7", True), ("D+14", False), ("Alta", False)]
    for i, (lab, done) in enumerate(steps):
        cx = 24 + i * 88
        col = (16, 185, 129, 255) if done else ((75, 114, 190, 255) if i == 2 else (148, 163, 184, 255))
        d.ellipse((cx, 348, cx + 14, 362), fill=col)
        d.text((cx - 8, 372), lab, font=fnt(14, True), fill=(71, 85, 105, 255))
        if i < 3:
            d.rectangle((cx + 18, 353, cx + 80, 357), fill=(203, 213, 225, 255))

    d.rounded_rectangle((14, 420, w - 14, 575), 14, fill=(255, 255, 255, 255), outline=(210, 222, 240, 255))
    d.text((28, 438), "Marina Alves", font=fnt(20, True), fill=(30, 42, 58, 255))
    d.text((28, 470), "Paciente · D+7 na Trilha", font=fnt(15, False), fill=(100, 116, 139, 255))
    d.rounded_rectangle((28, 510, 200, 552), 12, fill=(75, 114, 190, 255))
    d.text((48, 520), "Abrir ficha", font=fnt(16, True), fill=(255, 255, 255, 255))

    d.rectangle((0, h - 68, w, h), fill=(255, 255, 255, 255))
    d.line((0, h - 68, w, h - 68), fill=(226, 232, 240, 255), width=2)
    for lab, x, on in (("Início", 28, False), ("Pulso", 120, True), ("Pacientes", 210, False), ("Mais", 310, False)):
        d.text((x, h - 42), lab, font=fnt(13, True), fill=(75, 114, 190, 255) if on else (148, 163, 184, 255))
    return ui


def phone_device(ui: Image.Image) -> Image.Image:
    pw, ph = ui.width + 44, ui.height + 64
    phone = Image.new("RGBA", (pw, ph), (0, 0, 0, 0))
    d = ImageDraw.Draw(phone)
    d.rounded_rectangle((0, 0, pw - 1, ph - 1), 48, fill=(18, 18, 20, 255))
    d.rounded_rectangle((8, 8, pw - 9, ph - 9), 42, fill=(8, 8, 10, 255))
    # dynamic island
    d.rounded_rectangle((pw // 2 - 48, 18, pw // 2 + 48, 38), 12, fill=(0, 0, 0, 255))
    mask = Image.new("L", ui.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, ui.width - 1, ui.height - 1), 28, fill=255)
    phone.paste(ui, (22, 44), mask)
    return phone


def teal_bg() -> Image.Image:
    import numpy as np

    yy, xx = np.mgrid[0:H, 0:W]
    t = yy / H
    r = (6 + 8 * t).astype(np.float32)
    g = (28 + 55 * (1 - np.abs(t - 0.35))).astype(np.float32)
    b = (42 + 40 * (1 - t)).astype(np.float32)
    glow = np.clip(
        1 - np.sqrt((xx - W * 0.85) ** 2 + (yy - H * 0.35) ** 2) / 700,
        0,
        1,
    )
    g = np.clip(g + 90 * glow, 0, 255)
    b = np.clip(b + 60 * glow, 0, 255)
    arr = np.stack([r, g, b], axis=-1).astype(np.uint8)
    return Image.fromarray(arr, "RGB").convert("RGBA")


def draw_text_block(
    canvas: Image.Image,
    *,
    badge: str,
    lines: list[tuple[str, bool]],
    cta: str,
    cta_solid: bool,
    x: int = 48,
    y: int = 72,
) -> None:
    d = ImageDraw.Draw(canvas)
    logo = fit(Image.open(LOGO).convert("RGBA"), 300, 140)
    canvas.alpha_composite(logo, (x, y))
    y += logo.height + 28

    # badge
    bw = int(d.textlength(badge, font=fnt(24, True))) + 40
    d.rounded_rectangle((x, y, x + bw, y + 48), 24, fill=(255, 255, 255, 245))
    d.text((x + 20, y + 10), badge, font=fnt(24, True), fill=(12, 36, 58, 255))
    y += 72

    for text, heavy in lines:
        f = fnt(48 if heavy else 40, True)
        # wrap roughly
        words = text.split()
        cur = ""
        for w in words:
            test = (cur + " " + w).strip()
            if d.textlength(test, font=f) > 560:
                d.text((x, y), cur, font=f, fill=(255, 255, 255, 255 if heavy else 235))
                y += 58 if heavy else 52
                cur = w
            else:
                cur = test
        if cur:
            d.text((x, y), cur, font=f, fill=(255, 255, 255, 255 if heavy else 235))
            y += 58 if heavy else 52

    y += 20
    if cta_solid:
        cw = int(d.textlength(cta, font=fnt(26, True))) + 48
        d.rounded_rectangle((x, y, x + cw, y + 58), 28, fill=(255, 255, 255, 245))
        d.text((x + 24, y + 14), cta, font=fnt(26, True), fill=(12, 36, 58, 255))
    else:
        cw = int(d.textlength(cta, font=fnt(26, True))) + 48
        d.rounded_rectangle((x, y, x + cw, y + 58), 28, outline=(255, 255, 255, 230), width=3)
        d.text((x + 24, y + 14), cta, font=fnt(26, True), fill=(255, 255, 255, 255))


def place_person_right(canvas: Image.Image, photo: Image.Image, opacity_fade: bool = True) -> None:
    ph = photo.convert("RGBA")
    # scale to cover right half
    scale = H / ph.height
    nw, nh = int(ph.width * scale), H
    if nw < W * 0.55:
        scale = (W * 0.62) / ph.width
        nw, nh = int(ph.width * scale), int(ph.height * scale)
    ph = ph.resize((nw, nh), Image.Resampling.LANCZOS)
    # crop center vertically if taller
    if nh > H:
        top = (nh - H) // 2
        ph = ph.crop((0, top, nw, top + H))
        nh = H
    x = W - nw + 40
    y = H - nh
    if opacity_fade:
        # soft left edge fade on person
        fade = Image.new("L", ph.size, 255)
        fd = ImageDraw.Draw(fade)
        for i in range(120):
            fd.rectangle((i, 0, i, nh), fill=int(255 * (i / 120)))
        ph.putalpha(fade)
    canvas.alpha_composite(ph, (x, max(0, y)))


def story_caveo_attention(photo_path: Path, out_name: str, lines: list[tuple[str, bool]], badge: str, cta: str) -> Path:
    canvas = teal_bg()
    place_person_right(canvas, Image.open(photo_path), opacity_fade=True)
    # darken left for reading
    ov = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    od = ImageDraw.Draw(ov)
    for i in range(620):
        a = int(150 * (1 - i / 620))
        od.line([(i, 0), (i, H)], fill=(4, 18, 30, a))
    canvas = Image.alpha_composite(canvas, ov)

    draw_text_block(canvas, badge=badge, lines=lines, cta=cta, cta_solid=False, x=48, y=80)

    # small phone with UI floating lower-left
    logo = Image.open(LOGO).convert("RGBA")
    phone = phone_device(make_ui(logo))
    phone = phone.resize((int(phone.width * 0.72), int(phone.height * 0.72)), Image.Resampling.LANCZOS)
    # slight rotation
    phone = phone.rotate(8, expand=True, resample=Image.Resampling.BICUBIC)
    canvas.alpha_composite(phone, (40, H - phone.height - 80))

    out = CURSOR / out_name
    canvas.convert("RGB").save(out, "PNG")
    canvas.convert("RGB").save(OUT / out_name, "PNG")
    print("OK", out)
    return out


def story_photo_with_ui(photo_path: Path, out_name: str, phone_box: tuple[int, int, int, int], lines: list[tuple[str, bool]], badge: str, cta: str) -> Path:
    """Liked lifestyle photo + UI inside phone + Caveo-like text on dark left panel."""
    photo = Image.open(photo_path).convert("RGBA")
    # cover full story
    scale = max(W / photo.width, H / photo.height)
    nw, nh = int(photo.width * scale), int(photo.height * scale)
    photo = photo.resize((nw, nh), Image.Resampling.LANCZOS)
    left = (nw - W) // 2
    top = (nh - H) // 2
    canvas = photo.crop((left, top, left + W, top + H))

    # dark teal left overlay like Caveo
    ov = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    od = ImageDraw.Draw(ov)
    for i in range(0, 580):
        a = int(185 * (1 - i / 580) ** 0.7)
        od.line([(i, 0), (i, H)], fill=(5, 26, 40, a))
    # bottom fade
    for j in range(0, 280):
        a = int(120 * (j / 280))
        od.line([(0, H - 1 - j), (W, H - 1 - j)], fill=(5, 20, 32, a))
    canvas = Image.alpha_composite(canvas, ov)

    logo = Image.open(LOGO).convert("RGBA")
    ui = make_ui(logo)
    x0, y0, x1, y1 = phone_box
    target = ui.resize((x1 - x0, y1 - y0), Image.Resampling.LANCZOS)
    mask = Image.new("L", target.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, target.width - 1, target.height - 1), 36, fill=255)
    canvas.paste(target, (x0, y0), mask)

    draw_text_block(canvas, badge=badge, lines=lines, cta=cta, cta_solid=True, x=40, y=64)

    out = CURSOR / out_name
    canvas.convert("RGB").save(out, "PNG")
    canvas.convert("RGB").save(OUT / out_name, "PNG")
    print("OK", out)
    return out


def main() -> None:
    medica = CURSOR / "ref-medica-phone.png"
    medico = CURSOR / "ref-medico-phone.png"
    # Also try flat gens if refs are small — upscale refs
    # Prefer larger lifestyle if available
    flat1 = CURSOR / "unio-story-flat-1.png"
    flat2 = CURSOR / "unio-story-flat-2.png"

    photo_m = flat2 if flat2.exists() else medico
    photo_f = flat1 if flat1.exists() else medica

    story_photo_with_ui(
        photo_f,
        "unio-story-texto-1.png",
        phone_box=(390, 520, 820, 1420),
        lines=[
            ("A cirurgia termina.", False),
            ("O cuidado, não.", True),
        ],
        badge="Atenção!",
        cta="Saiba mais!",
    )
    story_photo_with_ui(
        photo_m,
        "unio-story-texto-2.png",
        phone_box=(340, 560, 760, 1460),
        lines=[
            ("No app da Unio Saúde,", False),
            ("você acompanha cada paciente depois da alta.", True),
        ],
        badge="Unio Saúde",
        cta="Saiba mais!",
    )
    story_caveo_attention(
        photo_m,
        "unio-story-texto-3.png",
        lines=[
            ("Você cuida do paciente.", False),
            ("nós cuidamos do acompanhamento.", True),
        ],
        badge="Atenção!",
        cta="A Unio ajuda você! →",
    )


if __name__ == "__main__":
    main()
