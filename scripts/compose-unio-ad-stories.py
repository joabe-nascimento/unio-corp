"""5 Instagram Stories Unio Saúde — fontes da marca (Nunito/Quicksand) + logo sem fundo."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parents[1]
CURSOR_ASSETS = Path(r"C:\Users\joabe\.cursor\projects\c-projetos-Nova-pasta-unio-corp\assets")
OUT = ROOT / "public" / "images" / "marketing" / "ads"
FONTS = ROOT / "assets" / "fonts"
OUT.mkdir(parents=True, exist_ok=True)

LOGO_CANDIDATES = [
    ROOT / "public" / "images" / "logos" / "unio-saude.png",
    ROOT / "assets" / "unio-saude.png",
]
W, H = 1080, 1920

# Identidade Unio Saúde: Nunito (display) + Quicksand (corpo) — ver unio-organismo.css
STORIES = [
    {
        "bg": "story-bg-01-cuidado.png",
        "out": "unio-ad-story-01-cuidado.png",
        "eyebrow": "Unio Saúde",
        "title": "A cirurgia termina.\nO cuidado continua.",
        "body": "Acompanhe o paciente depois da alta\ncom protocolo, alertas e clareza.",
        "cta": "Conhecer a Unio",
        "tone": "light",
    },
    {
        "bg": "story-bg-02-pulso.png",
        "out": "unio-ad-story-02-pulso.png",
        "eyebrow": "Pulso",
        "title": "A clínica inteira,\nem um olhar.",
        "body": "Casos ativos, prioridades\ne o que pede atenção agora.",
        "cta": "Abrir o Pulso",
        "tone": "dark",
    },
    {
        "bg": "story-bg-03-trilha.png",
        "out": "unio-ad-story-03-trilha.png",
        "eyebrow": "Trilha Unio",
        "title": "Recuperação\npasso a passo.",
        "body": "Marcos claros: D+1, D+7 e D+14\naté a alta segura.",
        "cta": "Conhecer a Trilha",
        "tone": "light",
    },
    {
        "bg": "story-bg-04-sala.png",
        "out": "unio-ad-story-04-sala.png",
        "eyebrow": "Sala Crítica",
        "title": "Urgente entra\nna frente.",
        "body": "Organize os casos prioritários\ne foque no que não pode esperar.",
        "cta": "Ver a Sala Crítica",
        "tone": "dark",
    },
    {
        "bg": "story-bg-05-portal.png",
        "out": "unio-ad-story-05-portal.png",
        "eyebrow": "Portal do paciente",
        "title": "O cuidado segue\nno celular.",
        "body": "Carteirinha, protocolo e canal\nde acompanhamento na mão do paciente.",
        "cta": "Conhecer o Portal",
        "tone": "light",
    },
]


def font_path(name: str) -> str:
    p = FONTS / name
    if not p.exists():
        raise FileNotFoundError(f"Fonte ausente: {p}")
    return str(p)


def f_display(size: int):
    return ImageFont.truetype(font_path("Nunito-ExtraBold.ttf"), size)


def f_display_sm(size: int):
    return ImageFont.truetype(font_path("Nunito-Bold.ttf"), size)


def f_body(size: int):
    return ImageFont.truetype(font_path("Quicksand-Medium.ttf"), size)


def f_ui(size: int):
    return ImageFont.truetype(font_path("Quicksand-SemiBold.ttf"), size)


def load_logo() -> Image.Image:
    for p in LOGO_CANDIDATES:
        if p.exists():
            return Image.open(p).convert("RGBA")
    raise FileNotFoundError("Logo unio-saude.png não encontrado")


def cover(im: Image.Image, tw: int, th: int) -> Image.Image:
    src = im.convert("RGBA")
    sw, sh = src.size
    scale = max(tw / sw, th / sh)
    nw, nh = int(sw * scale), int(sh * scale)
    resized = src.resize((nw, nh), Image.Resampling.LANCZOS)
    x = (nw - tw) // 2
    y = (nh - th) // 2
    return resized.crop((x, y, x + tw, y + th))


def soft_vignette(base: Image.Image, tone: str) -> Image.Image:
    overlay = Image.new("RGBA", base.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    top_h = 580 if tone == "dark" else 600
    bot_h = 520 if tone == "dark" else 540
    top_a = 210 if tone == "dark" else 175
    bot_a = 185 if tone == "dark" else 155
    for i in range(top_h):
        a = int(top_a * (1 - i / top_h) ** 0.85)
        d.rectangle((0, i, W, i + 1), fill=(6, 14, 28, a))
    for i in range(bot_h):
        a = int(bot_a * (i / bot_h) ** 0.9)
        y = H - bot_h + i
        d.rectangle((0, y, W, y + 1), fill=(4, 10, 22, a))
    out = base.copy()
    out.alpha_composite(overlay.filter(ImageFilter.GaussianBlur(20)))
    return out


def fit_logo(logo: Image.Image, max_w: int, max_h: int) -> Image.Image:
    o = logo.copy()
    o.thumbnail((max_w, max_h), Image.Resampling.LANCZOS)
    return o


def paste_logo_clean(canvas: Image.Image, logo: Image.Image, x: int, y: int) -> tuple[int, int]:
    """Cola o logo oficial sem placa branca — só sombra suave para contraste."""
    mark = fit_logo(logo, 400, 110)
    shadow = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    # sombra difusa sob o mark (sem retângulo branco)
    ink = Image.new("RGBA", mark.size, (0, 0, 0, 0))
    # usamos o alpha do logo como máscara de sombra
    alpha = mark.split()[3]
    shadow_mark = Image.new("RGBA", mark.size, (0, 0, 0, 0))
    shadow_mark.putalpha(alpha.point(lambda a: int(a * 0.45)))
    shadow.alpha_composite(shadow_mark, (x + 3, y + 5))
    shadow = shadow.filter(ImageFilter.GaussianBlur(8))
    canvas.alpha_composite(shadow)
    canvas.alpha_composite(mark, (x, y))
    return mark.width, mark.height


def draw_text_shadow(draw: ImageDraw.ImageDraw, xy, text, font, fill, shadow=(0, 0, 0, 100)):
    x, y = xy
    draw.text((x + 1, y + 2), text, font=font, fill=shadow)
    draw.text((x, y), text, font=font, fill=fill)


def compose_one(story: dict, logo: Image.Image) -> Path:
    bg_path = CURSOR_ASSETS / story["bg"]
    if not bg_path.exists():
        raise FileNotFoundError(bg_path)

    canvas = soft_vignette(cover(Image.open(bg_path), W, H), story["tone"])
    draw = ImageDraw.Draw(canvas)
    pad = 56

    mark_w, mark_h = paste_logo_clean(canvas, logo, pad, pad)
    draw = ImageDraw.Draw(canvas)

    # Eyebrow — pill com tipografia Quicksand (UI da marca)
    eye_f = f_ui(26)
    eyebrow = story["eyebrow"].upper()
    ew = int(draw.textlength(eyebrow, font=eye_f))
    eye_y = pad + mark_h + 40
    pill_w, pill_h = ew + 44, 46
    accent = (75, 114, 190, 235) if story["tone"] == "light" else (16, 185, 129, 230)
    draw.rounded_rectangle((pad, eye_y, pad + pill_w, eye_y + pill_h), 23, fill=accent)
    draw.text((pad + 22, eye_y + 9), eyebrow, font=eye_f, fill=(255, 255, 255, 255))

    # Título — Nunito ExtraBold (display da marca)
    title_f = f_display(68)
    title_y = eye_y + pill_h + 32
    for line in story["title"].split("\n"):
        draw_text_shadow(draw, (pad, title_y), line, title_f, (255, 255, 255, 255), (0, 0, 0, 110))
        bbox = draw.textbbox((0, 0), line, font=title_f)
        title_y += (bbox[3] - bbox[1]) + 10

    # Corpo — Quicksand Medium
    body_f = f_body(32)
    body_y = title_y + 22
    for line in story["body"].split("\n"):
        draw_text_shadow(draw, (pad, body_y), line, body_f, (220, 232, 246, 250), (0, 0, 0, 80))
        bbox = draw.textbbox((0, 0), line, font=body_f)
        body_y += (bbox[3] - bbox[1]) + 8

    # CTA — Quicksand SemiBold
    cta_f = f_ui(30)
    cta = story["cta"]
    cw = int(draw.textlength(cta, font=cta_f))
    cta_h = 68
    cta_w = cw + 64
    cta_x, cta_y = pad, H - 220
    draw.rounded_rectangle((cta_x, cta_y, cta_x + cta_w, cta_y + cta_h), 34, fill=(17, 158, 249, 255))
    draw.text((cta_x + 32, cta_y + 17), cta, font=cta_f, fill=(255, 255, 255, 255))

    # URL
    foot = f_body(22)
    draw.text((pad, H - 108), "uniosaude.uniowork.com.br", font=foot, fill=(196, 212, 232, 220))

    out = OUT / story["out"]
    canvas.convert("RGB").save(out, "PNG", optimize=True)
    print(f"OK {out.name}")
    return out


def main():
    logo = load_logo()
    print(f"Logo: {logo.size} | Fonts: Nunito + Quicksand")
    for s in STORIES:
        compose_one(s, logo)
    print(f"\nSaída: {OUT}")


if __name__ == "__main__":
    main()
