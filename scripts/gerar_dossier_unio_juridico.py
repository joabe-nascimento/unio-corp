#!/usr/bin/env python3
"""
Dossiê institucional — Unio Jurídico
PDF editorial (capa, sumário, seções, rodapé, continuações).
"""

from __future__ import annotations

import os
from datetime import datetime
from pathlib import Path

from reportlab.lib.colors import HexColor, white
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    KeepTogether,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
    Flowable,
)

# ── paths ──────────────────────────────────────────────────────────────────
ROOT = Path(__file__).resolve().parents[1]
LOGO = ROOT / "public" / "images" / "logos" / "unio-juridico.png"
if not LOGO.exists():
    LOGO = ROOT / "assets" / "logo-uniojuridico.png"
OUT_DIR = ROOT / "var" / "docs"
OUT_DIR.mkdir(parents=True, exist_ok=True)
OUT_PDF = OUT_DIR / "Unio-Juridico-Dossie-Institucional.pdf"

# ── palette ────────────────────────────────────────────────────────────────
BORDO = HexColor("#7A2130")
BORDO_ESC = HexColor("#5C1824")
BORDO_SOFT = HexColor("#F3E6E9")
INK = HexColor("#1A1A1A")
INK_2 = HexColor("#3A3A3A")
MUTED = HexColor("#6B6B6B")
LINE = HexColor("#D9D0D2")
PAPER = HexColor("#FAFAF8")
RULE = HexColor("#C4B4B8")

PAGE_W, PAGE_H = A4
MARGIN_L = 2.4 * cm
MARGIN_R = 2.4 * cm
MARGIN_T = 2.1 * cm
MARGIN_B = 2.3 * cm
CONTENT_W = PAGE_W - MARGIN_L - MARGIN_R  # ~16.2 cm

DOC_TITLE = "Unio Jurídico"
DOC_SUBTITLE = "Dossiê Institucional do Produto"
DOC_VERSION = "Versão 1.4.1"
_meses = {
    "January": "Janeiro", "February": "Fevereiro", "March": "Março",
    "April": "Abril", "May": "Maio", "June": "Junho",
    "July": "Julho", "August": "Agosto", "September": "Setembro",
    "October": "Outubro", "November": "Novembro", "December": "Dezembro",
}
DOC_DATE = f"{_meses.get(datetime.now().strftime('%B'), datetime.now().strftime('%B'))} de {datetime.now().year}"
SITE = "https://uniojuridico.uniowork.com.br"
CONTACT_EMAIL = "joabe@uniowork.com.br"

# Catálogo alinhado a src/Config/JuridicoModuleRegistry.php
SECTION_LABELS = {
    "contencioso": "Contencioso & Processual",
    "relacionamento": "Clientes & Relacionamento",
    "producao": "Produção & Documentos",
    "consultivo": "Consultivo & Contratos",
    "financeiro": "Financeiro do Escritório",
    "inteligencia": "Inteligência Jurídica",
    "governanca": "Governança & Ética",
}

STATUS_LABELS = {
    "beta": "Beta — disponível",
    "alpha": "Alpha — em desenvolvimento",
    "planned": "Planejado — roadmap",
}

MODULE_CATALOG = [
    ("contencioso", "Processos", "beta", "Carteira, KPIs, kanban de fases, tarefas, partes e alertas de risco"),
    ("contencioso", "Prazos & Diligências", "alpha", "Motor de prazos CPC, fila crítica e cálculo assistido pela Sasha"),
    ("contencioso", "Audiências", "planned", "Agenda, preparação, links virtuais e ata assistida"),
    ("contencioso", "Publicações & Intimações", "planned", "Captura DJe/DJEN, triagem por IA e abertura de prazos"),
    ("contencioso", "Integração Tribunais", "alpha", "Andamentos oficiais via DataJud (CNJ), também no chat"),
    ("relacionamento", "CRM Jurídico", "alpha", "Clientes, leads, pipeline e contexto comercial ligado à carteira"),
    ("relacionamento", "Portal do Cliente", "planned", "Transparência, documentos e self-service para o cliente"),
    ("relacionamento", "Central de Atendimento", "planned", "WhatsApp, e-mail e tickets unificados"),
    ("producao", "GED Jurídico", "alpha", "Repositório documental com versionamento e apoio da Sasha"),
    ("producao", "Petições & Modelos", "planned", "Biblioteca de modelos e montagem assistida de peças"),
    ("producao", "Assinatura & Protocolo", "planned", "Assinatura eletrônica e protocolo integrado"),
    ("consultivo", "Contratos", "alpha", "Ciclo de vida contratual, riscos e renovações"),
    ("consultivo", "Due Diligence", "planned", "M&A, auditoria legal e checklists de risco"),
    ("consultivo", "Societário & M&A", "planned", "Atos societários, assembleias e fusões"),
    ("financeiro", "Honorários & Timesheet", "alpha", "Apontamento de horas, tabelas OAB e faturamento"),
    ("financeiro", "Cobrança & Inadimplência", "planned", "Recebíveis, régua de cobrança e recuperação"),
    ("inteligencia", "Jurisprudência IA", "beta", "Pesquisa STF/STJ/TRTs com resumos, citações e favoritos"),
    ("inteligencia", "Analytics Jurídico", "beta", "BI de carteira: status, fase, receita, SLA e multi-escritório"),
    ("inteligencia", "Previsão de Êxito", "alpha", "Score explicável de probabilidade de êxito por processo"),
    ("inteligencia", "Agente Autônomo 24/7", "beta", "Varredura periódica de prazos, tarefas e alertas proativos"),
    ("inteligencia", "Orquestração IA · Modo Lex", "beta", "Router de intenções, agents com tools e raciocínio Lex"),
    ("governanca", "API Pública", "beta", "REST API v1 com tokens para integrações externas"),
    ("governanca", "Compliance OAB & LGPD", "planned", "Políticas, incidentes e evidências para auditoria"),
    ("governanca", "Conflito de Interesses", "planned", "Ethical wall e verificação automática de conflitos"),
    ("governanca", "Auditoria de Atos", "planned", "Trilha imutável de acessos e alterações sensíveis"),
]


def _try_register_fonts() -> tuple[str, str, str]:
    candidates = [
        (
            r"C:\Windows\Fonts\georgia.ttf",
            r"C:\Windows\Fonts\georgiab.ttf",
            r"C:\Windows\Fonts\georgiai.ttf",
        ),
        (
            r"C:\Windows\Fonts\times.ttf",
            r"C:\Windows\Fonts\timesbd.ttf",
            r"C:\Windows\Fonts\timesi.ttf",
        ),
    ]
    for regular, bold, italic in candidates:
        if all(os.path.exists(p) for p in (regular, bold, italic)):
            pdfmetrics.registerFont(TTFont("DocSerif", regular))
            pdfmetrics.registerFont(TTFont("DocSerif-Bold", bold))
            pdfmetrics.registerFont(TTFont("DocSerif-Italic", italic))
            return "DocSerif", "DocSerif-Bold", "DocSerif-Italic"
    return "Times-Roman", "Times-Bold", "Times-Italic"


SERIF, SERIF_B, SERIF_I = _try_register_fonts()
SANS = "Helvetica"
SANS_B = "Helvetica-Bold"


class AccentRule(Flowable):
    def __init__(self, width=None, thickness=1.2, color=BORDO, spaceBefore=4, spaceAfter=8):
        Flowable.__init__(self)
        self._width = width
        self.thickness = thickness
        self.color = color
        self.spaceBefore = spaceBefore
        self.spaceAfter = spaceAfter

    def wrap(self, availWidth, availHeight):
        self.width = min(self._width or availWidth, availWidth)
        return self.width, self.thickness + self.spaceBefore + self.spaceAfter

    def draw(self):
        self.canv.setStrokeColor(self.color)
        self.canv.setLineWidth(self.thickness)
        self.canv.line(0, self.spaceAfter, self.width, self.spaceAfter)


class SectionNumber(Flowable):
    def __init__(self, number: str, title: str):
        Flowable.__init__(self)
        self.number = number
        self.title = title

    def wrap(self, availWidth, availHeight):
        self.width = availWidth
        self.height = 2.2 * cm
        return self.width, self.height

    def draw(self):
        c = self.canv
        box = 1.45 * cm
        c.setFillColor(BORDO_SOFT)
        c.rect(0, 0.35 * cm, box, box, fill=1, stroke=0)
        c.setFillColor(BORDO)
        c.setFont(SERIF_B, 14)
        c.drawCentredString(box / 2, 0.85 * cm, self.number)
        c.setFillColor(INK)
        c.setFont(SERIF_B, 14.5)
        c.drawString(box + 0.45 * cm, 0.95 * cm, self.title)
        c.setStrokeColor(RULE)
        c.setLineWidth(0.55)
        c.line(box + 0.45 * cm, 0.7 * cm, self.width, 0.7 * cm)


def build_styles():
    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(
        name="CoverEyebrow", fontName=SANS_B, fontSize=8.5, textColor=BORDO,
        leading=12, alignment=TA_CENTER, spaceAfter=8,
    ))
    styles.add(ParagraphStyle(
        name="CoverTitle", fontName=SERIF_B, fontSize=26, textColor=INK,
        leading=32, alignment=TA_CENTER, spaceAfter=8,
    ))
    styles.add(ParagraphStyle(
        name="CoverSub", fontName=SERIF_I, fontSize=12.5, textColor=INK_2,
        leading=17, alignment=TA_CENTER, spaceAfter=6,
    ))
    styles.add(ParagraphStyle(
        name="CoverMeta", fontName=SANS, fontSize=9, textColor=MUTED,
        leading=13, alignment=TA_CENTER,
    ))
    styles.add(ParagraphStyle(
        name="H1", fontName=SERIF_B, fontSize=15.5, textColor=INK,
        leading=20, spaceBefore=0, spaceAfter=8,
    ))
    styles.add(ParagraphStyle(
        name="H2", fontName=SERIF_B, fontSize=11.5, textColor=BORDO_ESC,
        leading=15, spaceBefore=12, spaceAfter=5,
    ))
    styles.add(ParagraphStyle(
        name="H3", fontName=SANS_B, fontSize=9.5, textColor=INK,
        leading=13, spaceBefore=8, spaceAfter=3,
    ))
    styles.add(ParagraphStyle(
        name="Body", fontName=SERIF, fontSize=10.2, textColor=INK_2,
        leading=14.8, alignment=TA_JUSTIFY, spaceAfter=7,
    ))
    styles.add(ParagraphStyle(
        name="BodyLead", fontName=SERIF, fontSize=11, textColor=INK,
        leading=16, alignment=TA_JUSTIFY, spaceAfter=10,
    ))
    styles.add(ParagraphStyle(
        name="DocBullet", fontName=SERIF, fontSize=10, textColor=INK_2,
        leading=14, leftIndent=6, spaceAfter=3,
    ))
    styles.add(ParagraphStyle(
        name="TOCEntry", fontName=SERIF, fontSize=10.2, textColor=INK, leading=17,
    ))
    styles.add(ParagraphStyle(
        name="TOCNum", fontName=SANS_B, fontSize=9, textColor=BORDO, leading=17,
    ))
    styles.add(ParagraphStyle(
        name="Quote", fontName=SERIF_I, fontSize=10.5, textColor=BORDO_ESC,
        leading=15, leftIndent=10, rightIndent=10, spaceBefore=6, spaceAfter=8,
    ))
    styles.add(ParagraphStyle(
        name="TableCell", fontName=SERIF, fontSize=8.7, textColor=INK_2, leading=11.5,
    ))
    styles.add(ParagraphStyle(
        name="TableHead", fontName=SANS_B, fontSize=8, textColor=white, leading=10.5,
    ))
    styles.add(ParagraphStyle(
        name="SmallCenter", fontName=SANS, fontSize=8.2, textColor=MUTED,
        leading=11.5, alignment=TA_CENTER,
    ))
    styles.add(ParagraphStyle(
        name="ContinueNote", fontName=SERIF_I, fontSize=8.5, textColor=MUTED,
        leading=11, alignment=TA_RIGHT, spaceBefore=4, spaceAfter=2,
    ))
    styles.add(ParagraphStyle(
        name="ContinueHead", fontName=SERIF_I, fontSize=8.5, textColor=BORDO,
        leading=11, alignment=TA_LEFT, spaceBefore=0, spaceAfter=8,
    ))
    styles.add(ParagraphStyle(
        name="Caption", fontName=SANS, fontSize=7.8, textColor=MUTED,
        leading=10, spaceBefore=2, spaceAfter=8, alignment=TA_LEFT,
    ))
    return styles


STYLES = build_styles()


def draw_cover(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(PAPER)
    canvas.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
    canvas.setFillColor(BORDO)
    canvas.rect(0, PAGE_H - 0.5 * cm, PAGE_W, 0.5 * cm, fill=1, stroke=0)
    canvas.setFillColor(BORDO_ESC)
    canvas.rect(0, 0, PAGE_W, 1.1 * cm, fill=1, stroke=0)
    canvas.setFillColor(white)
    canvas.setFont(SANS, 7.2)
    canvas.drawCentredString(
        PAGE_W / 2, 0.45 * cm,
        f"{SITE}  ·  Documento confidencial — uso institucional",
    )
    canvas.setStrokeColor(RULE)
    canvas.setLineWidth(0.5)
    inset = 1.5 * cm
    canvas.rect(inset, 1.55 * cm, PAGE_W - 2 * inset, PAGE_H - 2.4 * cm, fill=0, stroke=1)
    canvas.restoreState()


def draw_interior(canvas, doc):
    canvas.saveState()
    page = canvas.getPageNumber()
    if page <= 1:
        canvas.restoreState()
        return

    y_head = PAGE_H - 1.1 * cm
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.45)
    canvas.line(MARGIN_L, y_head, PAGE_W - MARGIN_R, y_head)
    canvas.setFillColor(MUTED)
    canvas.setFont(SANS, 7.2)
    canvas.drawString(MARGIN_L, y_head + 3, DOC_TITLE.upper())
    canvas.drawRightString(PAGE_W - MARGIN_R, y_head + 3, DOC_SUBTITLE)

    y_foot = 1.05 * cm
    canvas.line(MARGIN_L, y_foot + 0.42 * cm, PAGE_W - MARGIN_R, y_foot + 0.42 * cm)
    canvas.setFillColor(MUTED)
    canvas.setFont(SANS, 7.2)
    canvas.drawString(MARGIN_L, y_foot, "Unio Jurídico · Dossiê Institucional")
    # Page number without overlapping mark
    canvas.setFillColor(BORDO)
    canvas.setFont(SANS_B, 8)
    canvas.drawRightString(PAGE_W - MARGIN_R, y_foot, str(page))
    canvas.restoreState()


def p(text: str, style: str = "Body"):
    return Paragraph(text, STYLES[style])


def continua(secao: str = ""):
    label = "(a seção continua na página seguinte)" if not secao else f"(a seção “{secao}” continua na página seguinte)"
    return Paragraph(label, STYLES["ContinueNote"])


def continuacao(titulo: str = ""):
    label = f"(continuação{(' — ' + titulo) if titulo else ''})"
    return Paragraph(label, STYLES["ContinueHead"])


def fit_widths(ratios: list[float]) -> list[float]:
    """Normalize column ratios so total == CONTENT_W."""
    s = sum(ratios)
    return [CONTENT_W * (r / s) for r in ratios]


def module_status_summary() -> dict[str, int]:
    counts = {"beta": 0, "alpha": 0, "planned": 0}
    for _, _, status, _ in MODULE_CATALOG:
        counts[status] = counts.get(status, 0) + 1
    return counts


def module_catalog_rows(section_key: str | None = None) -> list[list[str]]:
    rows = []
    for sec, label, status, desc in MODULE_CATALOG:
        if section_key is not None and sec != section_key:
            continue
        rows.append([
            label,
            STATUS_LABELS.get(status, status),
            desc,
        ])
    return rows


def module_catalog_by_section() -> list:
    """Flowables: tabelas por eixo funcional (podem quebrar entre páginas)."""
    blocks = []
    for sec_key, sec_label in SECTION_LABELS.items():
        rows = module_catalog_rows(sec_key)
        if not rows:
            continue
        blocks.append(p(sec_label, "H3"))
        blocks.append(simple_table(
            ["Módulo", "Status", "Descrição"],
            rows,
            ratios=[2.6, 2.4, 5.0],
        ))
        blocks.append(Spacer(1, 6))
    return blocks


def simple_table(headers: list[str], rows: list[list[str]], ratios: list[float] | None = None):
    if ratios is None:
        ratios = [1.0] * len(headers)
    widths = fit_widths(ratios)
    head = [Paragraph(h, STYLES["TableHead"]) for h in headers]
    data = [head]
    for row in rows:
        data.append([Paragraph(c, STYLES["TableCell"]) for c in row])
    t = Table(data, colWidths=widths, hAlign="LEFT", repeatRows=1)
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), BORDO),
        ("TEXTCOLOR", (0, 0), (-1, 0), white),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [white, BORDO_SOFT]),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("GRID", (0, 0), (-1, -1), 0.3, LINE),
        ("BOX", (0, 0), (-1, -1), 0.65, BORDO),
    ]))
    return t


def keep_table(headers, rows, ratios=None, caption: str | None = None):
    """Keep small/medium tables together so rows don't orphan across pages."""
    block = []
    if caption:
        block.append(Paragraph(caption, STYLES["Caption"]))
    block.append(simple_table(headers, rows, ratios))
    return KeepTogether(block)


_SECTION_STARTED = False


def section_break(number: str, title: str):
    """Cada seção começa em página própria. Espaço sobrando fica em branco."""
    global _SECTION_STARTED
    parts = []
    if _SECTION_STARTED:
        parts.append(PageBreak())
    _SECTION_STARTED = True
    parts.extend([
        Spacer(1, 2),
        SectionNumber(number, title),
        Spacer(1, 6),
    ])
    return parts


def build_story():
    global _SECTION_STARTED
    _SECTION_STARTED = False
    story = []

    # ═══════════════ CAPA ═══════════════
    story.append(NextPageTemplate("Cover"))
    story.append(Spacer(1, 3.5 * cm))
    if LOGO.exists():
        from reportlab.platypus import Image as RLImage
        logo = RLImage(str(LOGO), width=5.8 * cm, height=2.75 * cm, kind="proportional")
        logo.hAlign = "CENTER"
        story.append(logo)
    else:
        story.append(p("UNIO JURÍDICO", "CoverTitle"))

    story.append(Spacer(1, 1.5 * cm))
    story.append(p("DOCUMENTO INSTITUCIONAL", "CoverEyebrow"))
    rule_tbl = Table(
        [[AccentRule(width=3.0 * cm, thickness=1.3, color=BORDO)]],
        colWidths=[CONTENT_W],
    )
    rule_tbl.setStyle(TableStyle([("ALIGN", (0, 0), (-1, -1), "CENTER")]))
    story.append(rule_tbl)
    story.append(Spacer(1, 0.3 * cm))
    story.append(p("Unio Jurídico", "CoverTitle"))
    story.append(p("Plataforma de gestão jurídica inteligente", "CoverSub"))
    story.append(Spacer(1, 0.7 * cm))
    story.append(p(
        "Dossiê de produto · Visão, capacidades e inteligência artificial",
        "CoverMeta",
    ))
    story.append(Spacer(1, 2.2 * cm))
    story.append(p(f"{DOC_VERSION}  ·  {DOC_DATE}", "CoverMeta"))
    story.append(p(SITE, "CoverMeta"))

    story.append(NextPageTemplate("Interior"))
    story.append(PageBreak())

    # ═══════════════ SUMÁRIO ═══════════════
    story.append(p("Sumário", "H1"))
    story.append(AccentRule(color=BORDO, thickness=1.0))
    toc = [
        ("01", "Apresentação"),
        ("02", "Proposta de valor"),
        ("03", "Visão geral da plataforma"),
        ("04", "Sasha — assistente jurídica"),
        ("05", "Studio e Modo Lex"),
        ("06", "Módulos e capacidades"),
        ("07", "Agente autônomo e operações"),
        ("08", "Integrações e dados"),
        ("09", "Segurança e governança"),
        ("10", "Roadmap e próximos passos"),
        ("11", "Público, uso e benefícios"),
        ("12", "Contato"),
    ]
    for num, title in toc:
        row = Table(
            [[Paragraph(num, STYLES["TOCNum"]), Paragraph(title, STYLES["TOCEntry"])]],
            colWidths=fit_widths([1.2, 8.8]),
        )
        row.setStyle(TableStyle([
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("TOPPADDING", (0, 0), (-1, -1), 2.5),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 2.5),
            ("LINEBELOW", (0, 0), (-1, -1), 0.25, LINE),
        ]))
        story.append(row)

    story.append(PageBreak())

    # ═══════════════ 01 ═══════════════
    story.extend(section_break("01", "Apresentação"))
    story.append(p(
        "O Unio Jurídico é uma plataforma digital de gestão para escritórios de advocacia "
        "que buscam operar com clareza, previsibilidade e inteligência aplicada. Mais do que "
        "um cadastro processual, o produto integra carteira, prazos, relacionamento com "
        "clientes, produção documental, indicadores financeiros e um copiloto de inteligência "
        "artificial especializado no domínio jurídico brasileiro — com revisão humana e "
        "rastreabilidade em cada etapa.",
        "BodyLead",
    ))
    story.append(p(
        "Este dossiê apresenta, em linguagem institucional, o estado atual do produto: "
        "módulos disponíveis, capacidades em maturação, integração da assistente Sasha ao "
        "fluxo diário do advogado e benefícios concretos para sócios, equipes e clientes. "
        "O documento serve como base para demonstrações comerciais, alinhamento estratégico "
        "e acompanhamento do roadmap de evolução.",
        "Body",
    ))
    story.append(p(
        "O Unio Jurídico nasceu da observação de um problema recorrente: ferramentas "
        "genéricas de gestão raramente conversam com o rito processual brasileiro, "
        "enquanto soluções puramente de IA costumam gerar texto sem vínculo com a "
        "carteira real do escritório. A proposta aqui é unir os dois mundos — operação "
        "estruturada e assistência inteligente — no mesmo produto.",
        "Body",
    ))
    story.append(p(
        "Justiça que acompanha. Gestão jurídica inteligente.",
        "Quote",
    ))

    # ═══════════════ 02 ═══════════════
    story.extend(section_break("02", "Proposta de valor"))
    story.append(p(
        "O escritório moderno enfrenta uma tensão permanente entre volume, prazo fatal "
        "e qualidade técnica. Perde-se tempo em triagem, em busca de andamentos, em "
        "cálculo manual de prazos e em releitura de peças longas. O Unio Jurídico foi "
        "pensado para reduzir essa fricção: centralizar a operação, antecipar riscos e "
        "colocar a inteligência artificial a serviço do trabalho jurídico — sempre com "
        "revisão humana e rastreabilidade.",
        "Body",
    ))
    story.append(p("Princípios de produto", "H2"))
    for item in [
        "<b>Operação unificada</b> — 25 módulos em sete eixos: processos, prazos, clientes, documentos, contratos, financeiro e inteligência.",
        "<b>Inteligência aplicada</b> — a assistente Sasha responde com contexto do escritório, acionando ferramentas reais do sistema.",
        "<b>Prioridade no risco</b> — alertas, semáforos e agente autônomo 24/7 para o que não pode passar despercebido.",
        "<b>Confiança e governança</b> — integrações oficiais (DataJud/CNJ), controle de acesso e proteção de dados (LGPD).",
        "<b>Experiência profissional</b> — interfaces sóbrias, pensadas para o ritmo do contencioso e do consultivo.",
        "<b>Dois modos de conversa</b> — chat rápido no drawer lateral e Studio imersivo para sessões longas de análise.",
    ]:
        story.append(Paragraph(f"•  {item}", STYLES["DocBullet"]))

    story.append(p("Para quem o produto foi feito", "H2"))
    story.append(p(
        "Sócios gestores que precisam de visão de carteira; advogados e estagiários "
        "na rotina de prazos e peças; equipes de backoffice que triam publicações; "
        "e áreas comerciais que acompanham clientes e honorários. Em todos os casos, "
        "a Sasha funciona como apoio — nunca como substituta da responsabilidade "
        "profissional do advogado.",
        "Body",
    ))


    # ═══════════════ 03 ═══════════════
    story.extend(section_break("03", "Visão geral da plataforma"))
    story.append(p(
        "A plataforma organiza o dia a dia do escritório em eixos funcionais. Cada eixo "
        "possui módulos próprios, com status de maturidade (fundação, avançado ou em evolução) "
        "e pontos de contato com a inteligência artificial.",
        "Body",
    ))
    story.append(p("Eixos funcionais", "H2"))
    story.append(keep_table(
        ["Eixo", "Foco"],
        [
            ["Contencioso &amp; Processual", "Processos, prazos, audiências, publicações e tribunais"],
            ["Clientes &amp; Relacionamento", "CRM jurídico, portal do cliente e atendimento"],
            ["Produção &amp; Documentos", "GED, petições, modelos, assinatura e protocolo"],
            ["Consultivo &amp; Contratos", "Contratos, due diligence e societário"],
            ["Financeiro do Escritório", "Honorários, timesheet e fluxo financeiro"],
            ["Inteligência Jurídica", "Analytics, previsão de êxito, Sasha, Lex e agentes"],
            ["Governança &amp; Ética", "Permissões, auditoria e conformidade"],
        ],
        ratios=[3.4, 6.6],
        caption="Tabela 1 — Organização funcional do Unio Jurídico.",
    ))
    story.append(p(
        "O ponto de entrada operacional é o <b>Pulso</b>: um painel executivo com indicadores "
        "da carteira, atalhos e visão do que exige atenção no dia. A partir dele, o usuário "
        "navega para módulos específicos ou abre a Sasha — no drawer lateral ou no Studio.",
        "Body",
    ))
    story.append(p(
        "A navegação lateral agrupa os módulos por eixo (Contencioso, Clientes, Produção, "
        "etc.), com destaque para a entrada da assistente jurídica. A Central Jurídica concentra "
        "catálogo e status dos módulos, útil para onboarding de novas equipes.",
        "Body",
    ))

    # ═══════════════ 04 ═══════════════
    story.extend(section_break("04", "Sasha — assistente jurídica"))
    story.append(p(
        "Sasha é a assistente jurídica da plataforma. Atua como copiloto conversacional "
        "do advogado: recebe perguntas em linguagem natural, utiliza ferramentas do sistema "
        "quando necessário e devolve respostas orientadas à prática — com tom profissional "
        "e foco em próximo passo.",
        "BodyLead",
    ))
    story.append(p(
        "Diferente de um chatbot genérico, a Sasha pode acionar intenções e ferramentas "
        "internas: listar prazos, analisar carteira, consultar andamento no DataJud, "
        "sugerir tarefas urgentes e apoiar a produção documental. Quando a pergunta exige "
        "raciocínio mais denso, o usuário pode ativar o Modo Lex.",
        "Body",
    ))
    story.append(p("Capacidades no chat", "H2"))
    story.append(keep_table(
        ["Capacidade", "Descrição"],
        [
            ["Prazos processuais", "Cálculo e listagem; destaque de críticos e vencidos"],
            ["Jurisprudência", "Pesquisa orientada (ex.: STJ) com fundamentos e referências"],
            ["Documentos", "Resumo de peças, análise de contratos e comparação textual"],
            ["Produção", "Apoio à geração de minutas e petições a partir do caso"],
            ["Carteira", "Análise da saúde da carteira e tarefas urgentes"],
            ["Tribunais", "Consulta de andamento oficial via DataJud (CNJ)"],
            ["Honorários", "Estimativas e apoio ao cálculo de honorários advocatícios"],
            ["Estratégia", "Leitura de risco, fundamentos e próximos passos (Modo Lex)"],
        ],
        ratios=[2.8, 7.2],
        caption="Tabela 2 — Capacidades disponíveis no chat da Sasha.",
    ))
    story.append(p(
        "O chat mantém histórico de conversas, permite anexar arquivos (PDF, DOCX, TXT), "
        "oferece atalhos de intenção (chips) e registra o contexto da sessão. A experiência "
        "pode ser usada no <b>drawer lateral</b> (uso rápido, sem sair da tela atual) ou no "
        "<b>Studio</b> (uso imersivo, tela dedicada).",
        "Body",
    ))
    story.append(p("Recursos da conversa", "H2"))
    for item in [
        "<b>Histórico</b> — conversas salvas por sessão, com retomada a qualquer momento.",
        "<b>Anexos</b> — envio de PDF, DOCX e TXT para análise, resumo ou comparação.",
        "<b>Atalhos</b> — chips de intenção para prazos, carteira, minuta, DataJud e Lex.",
        "<b>Alertas proativos</b> — aviso de prazos críticos na mensagem de boas-vindas.",
        "<b>Modo Lex</b> — toggle no cabeçalho para raciocínio ampliado em análises densas.",
    ]:
        story.append(Paragraph(f"•  {item}", STYLES["DocBullet"]))
    story.append(p("Como a Sasha trabalha, em linhas gerais", "H2"))
    for item in [
        "O usuário formula a pergunta ou escolhe um atalho (ex.: \"Prazos críticos\").",
        "A aplicação encaminha a mensagem ao motor de inteligência da plataforma, com contexto e modo (padrão ou Lex).",
        "Quando necessário, o serviço chama ferramentas internas para obter dados reais do escritório.",
        "A resposta retorna em linguagem clara, com recomendações e, se for o caso, próximos passos.",
        "O histórico permanece disponível para continuidade da conversa.",
    ]:
        story.append(Paragraph(f"•  {item}", STYLES["DocBullet"]))
    story.append(p(
        "Exemplos de perguntas que a Sasha já atende hoje: \"Liste os prazos críticos\", "
        "\"Analise a saúde da minha carteira\", \"Calcule o prazo para apelação\", "
        "\"Pesquise jurisprudência no STJ sobre dano moral\" e \"Monte uma estratégia "
        "Lex para este contencioso\".",
        "Body",
    ))
    story.append(p(
        "A Sasha não substitui o julgamento profissional. Peças, estratégias e decisões "
        "permanecem sob responsabilidade do advogado responsável pelo caso.",
        "Quote",
    ))
    story.append(p("Ferramentas acionáveis no chat", "H2"))
    story.append(simple_table(
        ["Ferramenta", "O que faz"],
        [
            ["analisar_carteira", "Varre KPIs e destaca processos críticos da carteira"],
            ["tarefas_urgentes", "Lista tarefas pendentes com prioridade"],
            ["consultar_datajud", "Busca andamento oficial de processo no CNJ"],
            ["listar_prazos", "Exibe prazos pendentes e vencidos"],
        ],
        ratios=[3.2, 6.8],
    ))
    story.append(p(
        "Essas ferramentas são disparadas pela própria Sasha quando a intenção do usuário "
        "exige dados do sistema — sem que o advogado precise navegar manualmente pelos módulos.",
        "Body",
    ))
    story.append(p("Drawer e Studio: quando usar", "H2"))
    for item in [
        "<b>Drawer lateral</b> — consultas rápidas durante o trabalho em outro módulo (processos, prazos, CRM).",
        "<b>Studio</b> — sessões longas de análise, produção de minuta ou estratégia com Lex.",
        "<b>Histórico</b> — retomar conversas anteriores em qualquer um dos dois modos.",
    ]:
        story.append(Paragraph(f"•  {item}", STYLES["DocBullet"]))
    story.append(p(
        "A Sasha também exibe alertas proativos na mensagem de boas-vindas quando há "
        "prazos críticos na carteira — convidando o usuário a agir antes que o risco se materialize.",
        "Body",
    ))

    # ═══════════════ 05 ═══════════════
    story.extend(section_break("05", "Studio e Modo Lex"))
    story.append(p("Studio Sasha", "H2"))
    story.append(p(
        "O Studio é o workspace dedicado de bate-papo: tela completa, sem a navegação "
        "habitual do sistema, pensada para sessões longas de análise e produção. "
        "A composição privilegia três colunas: atalhos do dia a dia, histórico de "
        "conversas e a conversa principal com a Sasha.",
        "Body",
    ))
    story.append(keep_table(
        ["Coluna", "Conteúdo"],
        [
            ["Atalhos (esquerda)", "Grupos Hoje, Produzir e Pesquisar; links para Agente 24/7, Processos, Analytics e Pulso"],
            ["Histórico (centro)", "Lista de conversas, nova conversa e limpeza do histórico"],
            ["Conversa (direita)", "Mensagens, chips de sugestão, anexos e campo de envio"],
        ],
        ratios=[3.0, 7.0],
        caption="Tabela 3 — Layout do Studio Sasha.",
    ))
    story.append(p(
        "Na coluna de atalhos, o usuário encontra grupos como <b>Hoje</b> (prazos críticos, "
        "tarefas urgentes, pulse da carteira), <b>Produzir</b> (minuta, contrato, resumo, "
        "cálculo de prazo) e <b>Pesquisar</b> (jurisprudência, DataJud, previsão de êxito, "
        "estratégia Lex).",
        "Body",
    ))
    story.append(p("Modo Lex", "H2"))
    story.append(p(
        "O Modo Lex eleva o raciocínio da assistente para análises mais densas — "
        "estratégia, risco e fundamento — com orçamento maior de raciocínio no modelo "
        "de linguagem. É ativado pelo usuário no cabeçalho do chat e permanece disponível "
        "tanto no drawer quanto no Studio. Em conversas analíticas, o Lex favorece "
        "respostas mais estruturadas, com leitura de cenário e recomendação de próximo passo.",
        "Body",
    ))
    story.append(p(
        "O Studio não substitui o chat rápido: ambos coexistem. O drawer serve à "
        "interrupção breve; o Studio serve ao trabalho concentrado.",
        "Quote",
    ))


    # ═══════════════ 06 ═══════════════
    story.extend(section_break("06", "Módulos e capacidades"))
    counts = module_status_summary()
    total = len(MODULE_CATALOG)
    story.append(p(
        f"O Unio Jurídico organiza <b>{total} módulos</b> em sete eixos funcionais, "
        f"conforme o catálogo oficial do produto. Destes, "
        f"<b>{counts['beta']}</b> estão em estágio <b>Beta</b> (disponíveis para uso), "
        f"<b>{counts['alpha']}</b> em <b>Alpha</b> (estrutura pronta, evolução acelerada) "
        f"e <b>{counts['planned']}</b> <b>planejados</b> no roadmap. A maturidade de cada "
        "módulo é transparente na Central Jurídica da plataforma.",
        "BodyLead",
    ))
    story.append(p("Panorama por estágio", "H2"))
    story.append(keep_table(
        ["Estágio", "Quantidade", "Significado"],
        [
            ["Beta — disponível", str(counts["beta"]),
             "Funcional em produção; refinamentos contínuos (ex.: Processos, Analytics, Sasha Lex)"],
            ["Alpha — em desenvolvimento", str(counts["alpha"]),
             "Base operacional pronta; integração com IA e automações em evolução"],
            ["Planejado — roadmap", str(counts["planned"]),
             "Visão e catálogo definidos; priorização conforme demanda de clientes"],
        ],
        ratios=[2.6, 1.4, 6.0],
        caption=f"Tabela 4 — Panorama de maturidade ({total} módulos no catálogo).",
    ))
    story.append(p("Módulos em Beta — disponíveis hoje", "H2"))
    story.append(p(
        "Estes módulos já entregam valor operacional e podem ser demonstrados em ambiente "
        "de produção:",
        "Body",
    ))
    beta_names = [label for _, label, status, _ in MODULE_CATALOG if status == "beta"]
    for name in beta_names:
        story.append(Paragraph(f"•  <b>{name}</b>", STYLES["DocBullet"]))
    story.append(p("Módulos em Alpha — em desenvolvimento ativo", "H2"))
    story.append(p(
        "Estrutura funcional implantada; a equipe de produto acelera integrações, "
        "automações e refinamento de UX:",
        "Body",
    ))
    alpha_rows = [[label, desc] for _, label, status, desc in MODULE_CATALOG if status == "alpha"]
    story.append(simple_table(
        ["Módulo", "Escopo atual"],
        alpha_rows,
        ratios=[3.0, 7.0],
    ))
    story.append(p("Módulos planejados — próximas ondas", "H2"))
    story.append(p(
        "Catálogo definido no roadmap; entrega priorizada conforme maturidade dos módulos "
        "centrais e feedback dos escritórios piloto:",
        "Body",
    ))
    planned_rows = [[label, desc] for _, label, status, desc in MODULE_CATALOG if status == "planned"]
    story.append(simple_table(
        ["Módulo", "Visão de produto"],
        planned_rows,
        ratios=[3.0, 7.0],
    ))
    story.append(p("Catálogo completo por eixo funcional", "H2"))
    story.append(p(
        "A tabela a seguir consolida todos os módulos do sistema, agrupados por área "
        "de atuação do escritório:",
        "Body",
    ))
    story.extend(module_catalog_by_section())
    story.append(p(
        "O módulo de <b>Processos</b> concentra boa parte da rotina operacional: cadastro "
        "estruturado (CNJ, tribunal, área, fase, cliente, responsável e valor), filtros "
        "por status, kanban com movimento entre fases e integração direta com a Sasha "
        "para análise de carteira. Nos módulos Alpha, a estratégia é entregar valor "
        "incremental — estrutura e IA primeiro; automação de canais (WhatsApp, e-mail) "
        "e refinamentos de UX em seguida.",
        "Body",
    ))

    # ═══════════════ 07 ═══════════════
    story.extend(section_break("07", "Agente autônomo e operações"))
    story.append(p(
        "Além do chat sob demanda, o Unio Jurídico possui uma camada de "
        "<b>Agente Autônomo</b> voltada à vigilância contínua da operação. "
        "Essa camada executa rotinas periódicas (por exemplo, a cada 30 minutos) para "
        "varrer riscos, prazos e sinais de atenção — sem depender de o usuário "
        "abrir o chat naquele instante.",
        "Body",
    ))
    story.append(p(
        "Na prática, a inteligência do produto se organiza em camadas complementares:",
        "Body",
    ))
    story.append(keep_table(
        ["Camada", "Papel"],
        [
            ["Chat Sasha", "Interação humana sob demanda, com ferramentas e histórico"],
            ["Ferramentas do sistema", "Ações concretas (listar prazos, carteira, DataJud etc.)"],
            ["Modo Lex", "Raciocínio ampliado para estratégia e análise densa"],
            ["Agente Autônomo 24/7", "Monitoramento periódico e alertas operacionais"],
        ],
        ratios=[3.2, 6.8],
        caption="Tabela 6 — Camadas de inteligência do produto.",
    ))
    story.append(p(
        "Essa separação importa: o chat responde quando o advogado pergunta; o agente "
        "trabalha em segundo plano para que o risco não dependa apenas da memória humana. "
        "Há, ainda, uma página administrativa do Agente Autônomo para acompanhar status "
        "e execuções.",
        "Body",
    ))

    # ═══════════════ 08 ═══════════════
    story.extend(section_break("08", "Integrações e dados"))
    story.append(p(
        "O produto prioriza integrações com fontes oficiais e canais úteis ao "
        "escritório brasileiro. A consulta ao DataJud (CNJ) já está disponível "
        "como ferramenta da Sasha. Outras integrações (calendário, WhatsApp, "
        "assinatura eletrônica) fazem parte do roadmap de canais.",
        "Body",
    ))
    story.append(keep_table(
        ["Integração", "Status", "Uso"],
        [
            ["DataJud / CNJ", "Disponível", "Andamentos oficiais via chat ou módulo"],
            ["Plataforma Unio", "Disponível", "Processos, prazos, clientes e tarefas"],
            ["E-mail / WhatsApp", "Roadmap", "Alertas de prazo e escalonamentos"],
            ["Calendário", "Roadmap", "Prazos e audiências unificados"],
            ["Assinatura eletrônica", "Roadmap", "Fluxo de protocolo e contratos"],
        ],
        ratios=[3.0, 2.2, 4.8],
        caption="Tabela 7 — Integrações e estágio.",
    ))
    story.append(p(
        "Os dados do escritório permanecem na aplicação principal. A IA consulta o que "
        "precisa, no momento da pergunta, por meio de ferramentas autenticadas — em vez "
        "de manter uma cópia desnecessária de toda a base.",
        "Body",
    ))

    # ═══════════════ 09 ═══════════════
    story.extend(section_break("09", "Segurança e governança"))
    story.append(p(
        "Acesso autenticado, segregação por perfil e comunicação segura entre os "
        "módulos da plataforma são requisitos de base. Credenciais e integrações "
        "sensíveis permanecem protegidas e fora do alcance do usuário final.",
        "Body",
    ))
    story.append(keep_table(
        ["Controle", "Descrição"],
        [
            ["Autenticação", "Login seguro de usuários na plataforma"],
            ["Autorização", "Perfis e papéis por função no escritório"],
            ["Comunicação IA", "Canal autenticado entre chat e dados do escritório"],
            ["Dados", "Informações do escritório consultadas sob demanda, com rastreio"],
            ["Histórico", "Conversas e ações registradas no fluxo operacional"],
            ["Credenciais", "Chaves e tokens gerenciados de forma segura"],
        ],
        ratios=[2.8, 7.2],
        caption="Tabela 8 — Controles de segurança e governança.",
    ))
    story.append(p(
        "A responsabilidade profissional permanece com o advogado: a plataforma "
        "oferece apoio, alertas e sugestões, mas não emite parecer autônomo sem "
        "supervisão humana.",
        "Body",
    ))


    # ═══════════════ 10 ═══════════════
    story.extend(section_break("10", "Roadmap e próximos passos"))
    story.append(p(
        "O produto evolui em ondas: consolidação da operação (processos, prazos, CRM), "
        "aprofundamento da inteligência (Studio, Lex, agente 24/7) e expansão de canais "
        "e integrações. As prioridades imediatas incluem maturidade dos módulos de "
        "prazos e publicações, analytics jurídico e endurecimento da operação em nuvem.",
        "Body",
    ))
    story.append(p(
        "A tabela abaixo resume o horizonte de produto. Os três horizontes foram "
        "mantidos juntos para leitura contínua — sem quebra de linha órfã entre páginas.",
        "Body",
    ))
    story.append(keep_table(
        ["Horizonte", "Ênfase"],
        [
            ["Curto prazo", "Estabilidade da Sasha, Studio, Lex e agente autônomo em produção"],
            ["Médio prazo", "Automação de publicações para prazo, analytics jurídico e portal do cliente"],
            ["Longo prazo", "Multi-escritório, API pública e ecossistema de integrações (canais e assinatura)"],
        ],
        ratios=[2.6, 7.4],
        caption="Tabela 9 — Horizontes de evolução do produto.",
    ))
    story.append(p("Detalhamento por horizonte", "H2"))
    story.append(p(
        "<b>Curto prazo.</b> Consolidar o que já está no ar: confiabilidade das respostas "
        "da Sasha, uso cotidiano do Studio, Modo Lex em análises estratégicas e agente "
        "autônomo com status visível.",
        "Body",
    ))
    story.append(p(
        "<b>Médio prazo.</b> Fechar o ciclo operação–inteligência: publicação que vira "
        "prazo, painéis de analytics com indicadores úteis ao sócio, e portal do cliente "
        "como canal de transparência. É a fase em que o produto deixa de ser \"assistente "
        "+ processos\" e passa a ser a espinha dorsal do escritório.",
        "Body",
    ))
    story.append(p(
        "<b>Longo prazo.</b> Escala: grupos de escritórios (matriz/filial), API pública "
        "com tokens para parceiros, e um ecossistema de integrações (WhatsApp, calendário, "
        "assinatura eletrônica). Nesse estágio, o Unio Jurídico se posiciona como "
        "plataforma — não apenas como ferramenta pontual.",
        "Body",
    ))

    # ═══════════════ 11 ═══════════════
    story.extend(section_break("11", "Público, uso e benefícios"))
    story.append(p(
        "Esta seção resume, de forma prática, quem se beneficia do Unio Jurídico "
        "e em quais situações do dia a dia a plataforma gera valor.",
        "Body",
    ))
    story.append(keep_table(
        ["Perfil", "Benefício principal"],
        [
            ["Sócio / gestor", "Visão de carteira, riscos e prioridades sem depender de planilha"],
            ["Advogado", "Copiloto para prazos, peças, contratos e pesquisa"],
            ["Estagiário / paralegal", "Atalhos e triagem mais rápida, com histórico rastreável"],
            ["Backoffice", "Organização de publicações, tarefas e acompanhamento"],
            ["Área comercial", "CRM ligado à operação e transparência com o cliente"],
        ],
        ratios=[3.0, 7.0],
        caption="Tabela 10 — Perfis e benefícios.",
    ))
    story.append(p("Situações típicas de uso", "H2"))
    for item in [
        "Abrir o Pulso pela manhã e identificar o que é crítico na carteira.",
        "Pedir à Sasha a lista de prazos pendentes e abrir o Studio para aprofundar um caso.",
        "Anexar um contrato e solicitar análise de riscos antes da reunião com o cliente.",
        "Consultar andamento oficial no DataJud sem sair do chat.",
        "Ativar o Modo Lex para montar estratégia de contencioso complexo.",
        "Acompanhar o Agente Autônomo para confirmar que a vigilância 24/7 está ativa.",
    ]:
        story.append(Paragraph(f"•  {item}", STYLES["DocBullet"]))
    story.append(p(
        "Em todas essas situações, o valor está na combinação: dados do escritório "
        "mais linguagem natural mais ferramentas reais. O resultado é menos tempo "
        "gasto em triagem e mais tempo disponível para a análise jurídica.",
        "Body",
    ))


    # ═══════════════ 12 ═══════════════
    story.extend(section_break("12", "Contato"))
    story.append(p(
        "Para demonstração do produto, proposta comercial, parcerias ou suporte técnico "
        "sobre o Unio Jurídico, utilize os canais oficiais abaixo:",
        "Body",
    ))
    story.append(Spacer(1, 0.2 * cm))
    story.append(keep_table(
        ["Campo", "Informação"],
        [
            ["Produto", "Unio Jurídico"],
            ["Endereço web", SITE],
            ["E-mail", CONTACT_EMAIL],
            ["Assistente", "Sasha — assistente jurídica integrada"],
            ["Workspace", "Studio Sasha (tela dedicada de análise e produção)"],
            ["Documento", f"{DOC_SUBTITLE} · {DOC_VERSION}"],
            ["Data", DOC_DATE],
        ],
        ratios=[2.8, 7.2],
        caption="Tabela 11 — Canais oficiais de contato.",
    ))
    story.append(p(
        f"O endereço <b>{CONTACT_EMAIL}</b> é a conta institucional ativa da Unio na HostGator, "
        "utilizada para atendimento comercial, demonstrações e suporte ao produto. "
        "Respostas em horário comercial, de segunda a sexta-feira.",
        "Body",
    ))
    story.append(Spacer(1, 0.8 * cm))
    story.append(AccentRule(color=BORDO, thickness=1.0))
    story.append(p(
        "Este documento é de caráter institucional e descreve o produto em evolução. "
        "Funcionalidades e prazos de entrega podem ser atualizados conforme a maturidade "
        "de cada módulo. Distribuição restrita a uso institucional e comercial autorizado.",
        "SmallCenter",
    ))
    story.append(Spacer(1, 0.5 * cm))
    story.append(p("Unio Jurídico — Justiça que acompanha.", "SmallCenter"))
    story.append(Spacer(1, 0.3 * cm))
    story.append(p(f"— Fim do documento · {DOC_VERSION} —", "SmallCenter"))

    return story


def main():
    doc = BaseDocTemplate(
        str(OUT_PDF),
        pagesize=A4,
        title="Unio Jurídico — Dossiê Institucional",
        author="Unio Jurídico",
        subject="Dossiê de produto",
        creator="Unio Jurídico",
        leftMargin=MARGIN_L,
        rightMargin=MARGIN_R,
        topMargin=MARGIN_T,
        bottomMargin=MARGIN_B,
    )

    frame_cover = Frame(
        MARGIN_L, MARGIN_B, CONTENT_W, PAGE_H - MARGIN_T - MARGIN_B,
        id="cover", showBoundary=0,
    )
    frame_int = Frame(
        MARGIN_L,
        MARGIN_B + 0.25 * cm,
        CONTENT_W,
        PAGE_H - MARGIN_T - MARGIN_B - 0.55 * cm,
        id="interior",
        showBoundary=0,
    )

    doc.addPageTemplates([
        PageTemplate(id="Cover", frames=frame_cover, onPage=draw_cover),
        PageTemplate(id="Interior", frames=frame_int, onPage=draw_interior),
    ])

    doc.build(build_story())
    print(f"OK: {OUT_PDF}")
    print(f"Size: {OUT_PDF.stat().st_size / 1024:.1f} KB")


if __name__ == "__main__":
    main()
