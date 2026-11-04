#!/usr/bin/env python3
"""Gera a apostila usando ReportLab, sem acesso à rede."""
from pathlib import Path
import re, html
import argparse
import shutil
import subprocess
import tempfile
from reportlab.pdfgen import canvas
from reportlab.platypus import (BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer,
    PageBreak, KeepTogether, Flowable, Image)
from reportlab.platypus.tableofcontents import TableOfContents
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib import colors
from reportlab.lib.utils import ImageReader
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'curso/hidroterapia-ortopedica.pdf'
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--compact',action='store_true',help='Compacta as imagens dentro do PDF com Ghostscript, preservando resolução, links e marcadores.')
args=parser.parse_args()
FONT=Path('/usr/share/fonts/truetype/dejavu')
for name,file in [('Body','DejaVuSans.ttf'),('Body-Bold','DejaVuSans-Bold.ttf'),('Body-Italic','DejaVuSans-Oblique.ttf')]:
    pdfmetrics.registerFont(TTFont(name,str(FONT/file)))
pdfmetrics.registerFontFamily('Body',normal='Body',bold='Body-Bold',italic='Body-Italic',boldItalic='Body-Bold')
NAVY=colors.HexColor('#102D40'); TEAL=colors.HexColor('#087F8C'); INK=colors.HexColor('#243846')
MUTED=colors.HexColor('#526977'); PALE=colors.HexColor('#EAF5F5')
W,H=595.28,841.89
styles=getSampleStyleSheet()
styles.add(ParagraphStyle(name='Text',fontName='Body',fontSize=10.5,leading=16,textColor=INK,spaceAfter=9))
styles.add(ParagraphStyle(name='Chapter',fontName='Body-Bold',fontSize=22,leading=27,textColor=NAVY,spaceAfter=20))
styles.add(ParagraphStyle(name='Section',fontName='Body-Bold',fontSize=12.5,leading=17,textColor=TEAL,spaceBefore=10,spaceAfter=6,keepWithNext=True))
styles.add(ParagraphStyle(name='BulletText',parent=styles['Text'],leftIndent=13,firstLineIndent=-10,spaceAfter=6))
styles.add(ParagraphStyle(name='SmallText',parent=styles['Text'],fontSize=8,leading=12,textColor=MUTED))
styles.add(ParagraphStyle(name='Caption',parent=styles['SmallText'],spaceBefore=6,spaceAfter=12))
styles.add(ParagraphStyle(name='TOCItem',fontName='Body',fontSize=10,leading=15,leftIndent=0,firstLineIndent=0,spaceBefore=7,textColor=INK))
styles.add(ParagraphStyle(name='CoverTitle',fontName='Body-Bold',fontSize=34,leading=42,textColor=colors.white,spaceAfter=20))
styles.add(ParagraphStyle(name='CoverSub',fontName='Body',fontSize=16,leading=24,textColor=colors.HexColor('#C5E9EB'),spaceAfter=18))
styles.add(ParagraphStyle(name='CoverSmall',parent=styles['CoverSub'],fontSize=10,leading=16))

class FigureIndex(TableOfContents):
    def notify(self, kind, stuff):
        if kind == 'FigureEntry':
            self.addEntry(*stuff)

class Diagram(Flowable):
    """Diagramas vetoriais qualitativos; não representam doses clínicas."""
    KINDS = {'cycle', 'physics', 'drag', 'leverage', 'mechanisms'}

    def __init__(self, kind):
        super().__init__()
        if kind not in self.KINDS:
            raise ValueError(f'Diagrama desconhecido: {kind}')
        self.kind = kind
        self.width = 475
        self.height = 210 if kind != 'cycle' else 180
        self.spaceBefore = 8
        self.spaceAfter = 14

    @staticmethod
    def _arrow(c, x0, y0, x1, y1, color=TEAL, width=1.6, head=5):
        import math
        angle = math.atan2(y1 - y0, x1 - x0)
        c.setStrokeColor(color)
        c.setFillColor(color)
        c.setLineWidth(width)
        c.line(x0, y0, x1, y1)
        path = c.beginPath()
        path.moveTo(x1, y1)
        path.lineTo(x1 - head * math.cos(angle - math.pi / 6),
                    y1 - head * math.sin(angle - math.pi / 6))
        path.lineTo(x1 - head * math.cos(angle + math.pi / 6),
                    y1 - head * math.sin(angle + math.pi / 6))
        path.close()
        c.drawPath(path, fill=1, stroke=0)

    @staticmethod
    def _text(c, text, x, top, width, size=9, color=INK, leading=None):
        style = ParagraphStyle('DiagramText', fontName='Body', fontSize=size,
                               leading=leading or size + 3, textColor=color)
        paragraph = Paragraph(text, style)
        _, height = paragraph.wrap(width, 1000)
        paragraph.drawOn(c, x, top - height)
        return top - height

    def draw(self):
        c = self.canv
        c.saveState()
        c.setFillColor(PALE)
        c.roundRect(0, 0, self.width, self.height, 10, fill=1, stroke=0)
        c.setFillColor(NAVY)
        c.setFont('Body-Bold', 10.5)
        titles = {
            'physics': 'EMPUXO E PRESSÃO HIDROSTÁTICA',
            'drag': 'ARRASTO: RELAÇÃO IDEALIZADA ENTRE VELOCIDADE E FORÇA',
            'leverage': 'MOMENTO: FORÇA E BRAÇO DE ALAVANCA',
            'mechanisms': 'DO RECURSO À FUNÇÃO: UMA CADEIA A SER TESTADA',
            'cycle': 'CICLO DE DECISÃO CLÍNICA',
        }
        c.drawString(16, self.height - 23, titles[self.kind])
        if self.kind == 'physics':
            self._physics(c)
        elif self.kind == 'drag':
            self._drag(c)
        elif self.kind == 'leverage':
            self._leverage(c)
        elif self.kind == 'mechanisms':
            self._mechanisms(c)
        else:
            self._cycle(c)
        c.restoreState()

    def _physics(self, c):
        # O bloco simboliza um volume; a linha d'água define sua parte imersa.
        c.setFillColor(colors.HexColor('#D2ECEF'))
        c.roundRect(20, 40, 211, 110, 5, fill=1, stroke=0)
        c.setFillColor(colors.HexColor('#B5D7DE'))
        c.setStrokeColor(NAVY)
        c.setLineWidth(1.2)
        c.roundRect(95, 64, 50, 102, 4, fill=1, stroke=1)
        c.setStrokeColor(TEAL)
        c.line(20, 150, 231, 150)
        c.setFillColor(MUTED)
        c.setFont('Body', 8)
        c.drawString(21, 157, 'Superfície')
        c.setFillColor(NAVY)
        c.setFont('Body', 8)
        c.drawCentredString(120, 117, 'Volume')
        c.drawCentredString(120, 105, 'imerso')
        for y, length in [(137, 13), (105, 27), (73, 41)]:
            self._arrow(c, 95 - length, y, 95, y)
            self._arrow(c, 145 + length, y, 145, y)
        self._arrow(c, 210, 63, 210, 135, NAVY, 2.3, 7)
        c.setFillColor(NAVY)
        c.setFont('Body-Bold', 8)
        c.drawCentredString(210, 48, 'E')
        self._text(c, '<b>E = ρ × g × V imerso</b>', 248, 163, 209, 10)
        self._text(c, 'Empuxo: resultante vertical para cima, ligada ao volume de fluido deslocado.',
                   248, 140, 209)
        self._text(c, '<b>P = ρ × g × h</b><br/>A pressão cresce com a profundidade e age em todas as direções.',
                   248, 97, 209)
        self._text(c, 'Setas laterais mostram apenas o aumento qualitativo da pressão.',
                   248, 48, 209, 8)
        self._text(c, 'Esquema sem escala: não informa percentuais de descarga nem carga articular.',
                   16, 22, 443, 8)

    def _drag(self, c):
        x0, y0, unit_x, unit_y = 46, 61, 90, 23
        self._arrow(c, x0, y0, 250, y0, MUTED, 1.2, 5)
        self._arrow(c, x0, y0, x0, 166, MUTED, 1.2, 5)
        c.setFont('Body', 8)
        c.setFillColor(INK)
        c.drawString(28, 165, 'F')
        c.drawString(252, 58, 'v')
        for v in (1, 2):
            x = x0 + unit_x * v
            c.line(x, y0 - 3, x, y0 + 3)
            c.drawCentredString(x, 47, str(v))
        for f in (1, 4):
            y = y0 + unit_y * f
            c.line(x0 - 3, y, x0 + 3, y)
            c.drawRightString(x0 - 8, y - 3, str(f))
        path = c.beginPath()
        path.moveTo(x0, y0)
        for step in range(1, 61):
            v = step / 30
            path.lineTo(x0 + unit_x * v, y0 + unit_y * v * v)
        c.setStrokeColor(TEAL)
        c.setLineWidth(2.2)
        c.drawPath(path)
        c.setFillColor(TEAL)
        for v in (1, 2):
            c.circle(x0 + unit_x * v, y0 + unit_y * v * v, 3, fill=1, stroke=0)
        self._text(c, 'Velocidade relativa (unidades arbitrárias)', 28, 36, 242, 8)
        self._text(c, '<b>F ∝ v²</b>', 278, 163, 180, 12)
        self._text(c, 'Mantidos ρ, Cd e área frontal constantes, dobrar v quadruplica F no modelo.',
                   278, 136, 180)
        self._text(c, 'Na prática, forma e fluxo variam. Isso não equivale a quadruplicar a carga articular.',
                   278, 83, 180)
        self._text(c, 'Curva conceitual; não representa uma medição de força muscular ou dose terapêutica.',
                   16, 21, 443, 8)

    def _leverage(self, c):
        for y, force_x, label in [(141, 115, 'd menor'), (77, 180, 'd maior')]:
            c.setStrokeColor(NAVY)
            c.setLineWidth(3)
            c.line(50, y, 206, y)
            c.setFillColor(NAVY)
            c.circle(50, y, 5, fill=1, stroke=0)
            self._arrow(c, force_x, y + 32, force_x, y, TEAL, 2, 6)
            c.setFillColor(TEAL)
            c.setFont('Body-Bold', 9)
            c.drawString(force_x + 7, y + 22, 'F')
            c.setStrokeColor(MUTED)
            c.setLineWidth(0.8)
            c.line(50, y - 13, force_x, y - 13)
            c.line(50, y - 17, 50, y - 9)
            c.line(force_x, y - 17, force_x, y - 9)
            c.setFillColor(INK)
            c.setFont('Body', 8)
            c.drawCentredString((50 + force_x) / 2, y - 27, label)
        c.setFont('Body', 8)
        c.drawString(24, 34, 'Ponto escuro = eixo; F atua perpendicularmente à barra.')
        self._text(c, '<b>M = F × d perpendicular</b>', 260, 164, 198, 10)
        self._text(c, 'Com a mesma força, uma distância perpendicular maior produz maior momento no eixo.',
                   260, 139, 198)
        self._text(c, 'Na água, força e direção também mudam com posição, velocidade e acessórios.',
                   260, 83, 198)
        self._text(c, 'Esquema mecânico, sem equivalência com uma articulação ou acessório específico.',
                   16, 22, 443, 8)

    def _mechanisms(self, c):
        boxes = [
            (16, 119, '1. PROPRIEDADE', 'Empuxo e arrasto'),
            (170, 119, '2. EFEITO IMEDIATO', 'Redistribuição de forças'),
            (324, 119, '3. EXERCÍCIO', 'Seleção da tarefa e dose'),
            (170, 48, '4. ADAPTAÇÃO', 'Repetição + recuperação'),
            (16, 48, '5. FUNÇÃO', 'Meta funcional medida'),
        ]
        for x, y, label, detail in boxes:
            c.setFillColor(TEAL)
            c.roundRect(x, y, 135, 44, 5, fill=1, stroke=0)
            c.setFillColor(colors.white)
            c.setFont('Body-Bold', 8)
            c.drawCentredString(x + 67.5, y + 28, label)
            c.setFont('Body', 8)
            c.drawCentredString(x + 67.5, y + 12, detail)
        self._arrow(c, 155, 141, 166, 141, NAVY, 1.5, 4)
        self._arrow(c, 309, 141, 320, 141, NAVY, 1.5, 4)
        c.setStrokeColor(NAVY)
        c.setLineWidth(1.5)
        c.line(391.5, 115, 391.5, 70)
        self._arrow(c, 391.5, 70, 309, 70, NAVY, 1.5, 4)
        self._arrow(c, 166, 70, 155, 70, NAVY, 1.5, 4)
        self._text(c, 'Resposta imediata não garante adaptação; a transferência para solo precisa ser medida.',
                   16, 25, 443, 8)

    def _cycle(self, c):
        labels = ['AVALIAR', 'DEFINIR META', 'DOSAR', 'REAVALIAR']
        for i, label in enumerate(labels):
            x = 15 + i * 116
            c.setFillColor(TEAL)
            c.roundRect(x, 83, 104, 43, 5, fill=1, stroke=0)
            c.setFillColor(colors.white)
            c.setFont('Body-Bold', 9)
            c.drawCentredString(x + 52, 101, label)
        c.setFillColor(INK)
        c.setFont('Body', 10)
        c.drawString(18, 52, 'Monitorar segurança, execução, sintomas e recuperação.')
        c.drawString(18, 31, 'Verificar a transferência para a atividade em solo.')

class Book(BaseDocTemplate):
    def __init__(self,*a,**kw):
        super().__init__(*a,**kw);self.bookmark_index=0
        self.addPageTemplates(PageTemplate(id='book',frames=[Frame(58,57,W-116,H-115,id='main',leftPadding=0,rightPadding=0,topPadding=0,bottomPadding=0)],onPage=self.decorate))
    def beforeDocument(self):
        self.bookmark_index=0
        self.figure_index=0
    def decorate(self,c,doc):
        c.saveState()
        if doc.page==1:
            c.setFillColor(NAVY);c.rect(0,0,W,H,fill=1,stroke=0)
            c.setFillColor(TEAL);c.circle(W+70,190,240,fill=1,stroke=0)
            c.setStrokeColor(colors.HexColor('#62BFC4'));c.setLineWidth(1)
            for y in [160,180,200]:c.bezier(0,y,180,y+70,350,y-70,W,y)
        else:
            c.setStrokeColor(TEAL);c.setLineWidth(2);c.line(58,H-37,W-58,H-37)
            c.setFont('Body',7);c.setFillColor(MUTED)
            c.drawString(58,H-28,'FISIOTERAPIA AQUÁTICA  /  ORTOPEDIA')
            c.drawString(58,33,'Material educacional • versão para revisão técnica • 2026')
            c.drawRightString(W-58,33,str(doc.page))
        c.restoreState()
    def afterFlowable(self,f):
        if isinstance(f,Paragraph) and f.style.name=='Chapter':
            text=f.getPlainText(); key=f'chapter-{self.bookmark_index}';self.bookmark_index+=1
            self.canv.bookmarkPage(key);self.canv.addOutlineEntry(text,key,0,False)
            self.notify('TOCEntry',(0,text,self.page,key))
        elif isinstance(f,Paragraph) and f.style.name=='Caption':
            title=f.getPlainText().split(':',1)[0]
            key=f'figure-{self.figure_index}';self.figure_index+=1
            self.canv.bookmarkPage(key)
            self.notify('FigureEntry',(0,title,self.page,key))

def inline(s):
    s=html.escape(s)
    s=re.sub(r'\*\*(.+?)\*\*',r'<b>\1</b>',s)
    s=re.sub(r'(https://[^\s<>]+)',r'<link href="\1" color="#087F8C">\1</link>',s)
    return s

def illustration(caption, relative_path):
    """Preserva proporção e mantém a legenda junto à imagem no PDF."""
    course_dir = (ROOT / 'curso').resolve()
    path = (course_dir / relative_path).resolve()
    if not path.is_relative_to(course_dir):
        raise ValueError(f'Imagem deve estar dentro de curso/: {relative_path}')
    if not path.is_file():
        raise FileNotFoundError(f'Imagem ausente: {path}')
    image_width, image_height = ImageReader(str(path)).getSize()
    scale = min((W - 116) / image_width, 260 / image_height)
    picture = Image(str(path), width=image_width * scale,
                    height=image_height * scale, hAlign='CENTER')
    return KeepTogether([picture, Paragraph(inline(caption), styles['Caption'])])

story=[]
story.extend([Spacer(1,90),Paragraph('FORMAÇÃO TEÓRICO-PRÁTICA',styles['CoverSmall']),
    Paragraph('Hidroterapia<br/>Ortopédica em<br/>Fisioterapia',styles['CoverTitle']),
    Paragraph('Fundamentos, raciocínio clínico<br/>e aplicação supervisionada',styles['CoverSub']),Spacer(1,25),
    Paragraph('16 módulos • casos clínicos • avaliação comentada<br/>12 roteiros de vídeos • fichas de atendimento',styles['CoverSmall']),Spacer(1,30),
    Paragraph('Para estudantes em fase final da graduação<br/>e fisioterapeutas',styles['CoverSmall']),
    Spacer(1,20),Paragraph('EDIÇÃO AMPLIADA E ILUSTRADA • 2026<br/>Material educacional para revisão técnico-científica',styles['CoverSmall']),PageBreak()])
story.append(Paragraph('Sumário',styles['Chapter']))
toc=TableOfContents();toc.levelStyles=[styles['TOCItem']];toc.dotsMinLevel=0;story.extend([toc,PageBreak()])
story.append(Paragraph('Guia das ilustrações',styles['Chapter']))
story.append(Paragraph('Pranchas de posicionamento e movimento para acompanhar a biblioteca prática. As imagens são ilustrativas; profundidade, amplitude e assistência são individualizadas.',styles['Text']))
figures=FigureIndex();figures.levelStyles=[styles['TOCItem']];figures.dotsMinLevel=0
story.extend([figures,Spacer(1,20),Paragraph('Os esquemas de empuxo, pressão, arrasto e alavancas aparecem nos módulos de fundamentos. A interpretação de cada ilustração deve acompanhar a explicação do mecanismo, os limites da evidência e a análise de resposta à tarefa.',styles['Text']),PageBreak()])
source=(ROOT/'curso/apostila.md').read_text()
lines=source.splitlines();buf=[];first=True

def flush():
    if buf:
        story.append(Paragraph(inline(' '.join(buf)),styles['Text']));buf.clear()

for line in lines:
    image_match = re.fullmatch(r'!\[(.+?)\]\(([^)]+)\)', line.strip())
    diagram_match = re.fullmatch(r'<!-- diagram:(cycle|physics|drag|leverage|mechanisms) -->', line.strip())
    if line.strip() == '<!-- pagebreak -->':
        flush();story.append(PageBreak())
    elif diagram_match:
        flush();story.append(Diagram(diagram_match.group(1)))
    elif image_match:
        flush();story.append(illustration(*image_match.groups()))
    elif line.startswith('# '):
        flush();title=line[2:]
        if not first:story.append(PageBreak())
        first=False;story.append(Paragraph(inline(title),styles['Chapter']))
        if title.startswith('Módulo 1 —'):story.extend([Diagram('cycle'),Spacer(1,14)])
        if title.startswith('Módulo 2 —'):story.extend([Diagram('physics'),Spacer(1,14)])
    elif line.startswith('## '):
        flush();story.append(Paragraph(inline(line[3:]),styles['Section']))
    elif not line.strip():flush()
    elif line.startswith('- '):
        flush();story.append(Paragraph('• '+inline(line[2:]),styles['BulletText']))
    elif re.match(r'^\d+\. ',line):
        flush();story.append(Paragraph(inline(line),styles['BulletText']))
    else:buf.append(line.strip())
flush()
doc=Book(str(OUT),pagesize=(W,H),title='Hidroterapia Ortopédica em Fisioterapia',author='Material educacional — revisão técnica pendente',subject='Apostila de formação teórico-prática em fisioterapia aquática',allowSplitting=1)
doc.multiBuild(story)
if args.compact:
    gs=shutil.which('gs')
    if not gs:
        raise RuntimeError('A opção --compact exige Ghostscript (comando gs). O PDF sem compactação foi gerado.')
    with tempfile.TemporaryDirectory(prefix='crimpf-pdf-') as directory:
        compact=Path(directory)/'apostila.pdf'
        subprocess.run([gs,'-q','-sDEVICE=pdfwrite','-dCompatibilityLevel=1.7',
            '-dNOPAUSE','-dBATCH','-dSAFER','-dAutoFilterColorImages=false',
            '-dColorImageFilter=/DCTEncode','-dAutoFilterGrayImages=false',
            '-dGrayImageFilter=/DCTEncode','-dJPEGQ=90',
            '-dDownsampleColorImages=false','-dDownsampleGrayImages=false',
            f'-sOutputFile={compact}',str(OUT)],check=True)
        if compact.stat().st_size<OUT.stat().st_size:
            shutil.copyfile(compact,OUT)
print(OUT)
