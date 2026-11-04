#!/usr/bin/env python3
"""Gera a apostila usando ReportLab, sem acesso à rede."""
from pathlib import Path
import re, html
from reportlab.pdfgen import canvas
from reportlab.platypus import (BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer,
    PageBreak, KeepTogether, Flowable)
from reportlab.platypus.tableofcontents import TableOfContents
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'curso/hidroterapia-ortopedica.pdf'
FONT=Path('/usr/share/fonts/truetype/dejavu')
for name,file in [('Body','DejaVuSans.ttf'),('Body-Bold','DejaVuSans-Bold.ttf'),('Body-Italic','DejaVuSans-Oblique.ttf')]:
    pdfmetrics.registerFont(TTFont(name,str(FONT/file)))
pdfmetrics.registerFontFamily('Body',normal='Body',bold='Body-Bold',italic='Body-Italic',boldItalic='Body-Bold')
NAVY=colors.HexColor('#102D40'); TEAL=colors.HexColor('#087F8C'); INK=colors.HexColor('#243846')
MUTED=colors.HexColor('#526977'); PALE=colors.HexColor('#EAF5F5')
W,H=595.28,841.89
styles=getSampleStyleSheet()
styles.add(ParagraphStyle(name='Text',fontName='Body',fontSize=9.5,leading=14,textColor=INK,spaceAfter=8))
styles.add(ParagraphStyle(name='Chapter',fontName='Body-Bold',fontSize=22,leading=27,textColor=NAVY,spaceAfter=20))
styles.add(ParagraphStyle(name='Section',fontName='Body-Bold',fontSize=12.5,leading=17,textColor=TEAL,spaceBefore=10,spaceAfter=6,keepWithNext=True))
styles.add(ParagraphStyle(name='BulletText',parent=styles['Text'],leftIndent=13,firstLineIndent=-10,spaceAfter=6))
styles.add(ParagraphStyle(name='SmallText',parent=styles['Text'],fontSize=8,leading=12,textColor=MUTED))
styles.add(ParagraphStyle(name='TOCItem',fontName='Body',fontSize=10,leading=15,leftIndent=0,firstLineIndent=0,spaceBefore=7,textColor=INK))
styles.add(ParagraphStyle(name='CoverTitle',fontName='Body-Bold',fontSize=34,leading=42,textColor=colors.white,spaceAfter=20))
styles.add(ParagraphStyle(name='CoverSub',fontName='Body',fontSize=16,leading=24,textColor=colors.HexColor('#C5E9EB'),spaceAfter=18))
styles.add(ParagraphStyle(name='CoverSmall',parent=styles['CoverSub'],fontSize=10,leading=16))

class Diagram(Flowable):
    def __init__(self,kind):
        Flowable.__init__(self);self.kind=kind;self.width=475;self.height=180
    def draw(self):
        c=self.canv;c.setFillColor(PALE);c.roundRect(0,0,self.width,self.height,10,fill=1,stroke=0)
        c.setFillColor(NAVY);c.setFont('Body-Bold',11)
        if self.kind=='physics':
            c.drawString(16,156,'EMPuxo E PRESSÃO: ESQUEMA CONCEITUAL'.upper())
            c.setStrokeColor(TEAL);c.line(20,124,455,124)
            c.setFont('Body',9);c.drawString(25,132,'Superfície da água')
            c.setFillColor(NAVY);c.circle(175,100,9,fill=1,stroke=0)
            c.setLineWidth(4);c.setStrokeColor(NAVY);c.line(175,90,175,55);c.line(175,55,159,30);c.line(175,55,191,30)
            c.line(175,80,153,65);c.line(175,80,197,65)
            c.setLineWidth(2);c.setStrokeColor(TEAL)
            c.line(220,37,220,99);c.line(220,99,215,89);c.line(220,99,225,89)
            c.setFillColor(INK);c.setFont('Body',9);c.drawString(237,80,'Empuxo: força ascendente')
            c.drawString(237,61,'Pressão: aumenta com a profundidade')
            c.drawString(237,42,'Carga real: depende da tarefa e da pessoa')
            c.setFont('Body',8);c.drawString(16,12,'Ilustração didática, sem proporções ou percentuais clínicos de descarga.')
        else:
            c.drawString(16,156,'CICLO DE DECISÃO CLÍNICA')
            labels=['AVALIAR','DEFINIR META','DOSAR','REAVALIAR']
            for i,label in enumerate(labels):
                x=15+i*116;c.setFillColor(TEAL);c.roundRect(x,83,104,43,5,fill=1,stroke=0)
                c.setFillColor(colors.white);c.setFont('Body-Bold',9);c.drawCentredString(x+52,101,label)
            c.setFillColor(INK);c.setFont('Body',10)
            c.drawString(18,52,'Monitorar segurança, execução, sintomas e recuperação.')
            c.drawString(18,31,'Verificar a transferência para a atividade em solo.')

class Book(BaseDocTemplate):
    def __init__(self,*a,**kw):
        super().__init__(*a,**kw);self.bookmark_index=0
        self.addPageTemplates(PageTemplate(id='book',frames=[Frame(58,57,W-116,H-115,id='main',leftPadding=0,rightPadding=0,topPadding=0,bottomPadding=0)],onPage=self.decorate))
    def beforeDocument(self):
        self.bookmark_index=0
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

def inline(s):
    s=html.escape(s)
    s=re.sub(r'\*\*(.+?)\*\*',r'<b>\1</b>',s)
    s=re.sub(r'(https://[^\s<>]+)',r'<link href="\1" color="#087F8C">\1</link>',s)
    return s

story=[]
story.extend([Spacer(1,90),Paragraph('FORMAÇÃO TEÓRICO-PRÁTICA',styles['CoverSmall']),
    Paragraph('Hidroterapia<br/>Ortopédica em<br/>Fisioterapia',styles['CoverTitle']),
    Paragraph('Fundamentos, raciocínio clínico<br/>e aplicação supervisionada',styles['CoverSub']),Spacer(1,25),
    Paragraph('16 módulos • casos clínicos • avaliação comentada<br/>12 roteiros de vídeos • fichas de atendimento',styles['CoverSmall']),Spacer(1,30),
    Paragraph('Para estudantes em fase final da graduação<br/>e fisioterapeutas',styles['CoverSmall']),
    Spacer(1,20),Paragraph('EDIÇÃO 2026<br/>Material educacional para revisão técnico-científica',styles['CoverSmall']),PageBreak()])
story.append(Paragraph('Sumário',styles['Chapter']))
toc=TableOfContents();toc.levelStyles=[styles['TOCItem']];toc.dotsMinLevel=0;story.extend([toc,PageBreak()])
source=(ROOT/'curso/apostila.md').read_text()
lines=source.splitlines();buf=[];first=True

def flush():
    if buf:
        story.append(Paragraph(inline(' '.join(buf)),styles['Text']));buf.clear()

for line in lines:
    if line.startswith('# '):
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
print(OUT)
