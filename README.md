# Hidroterapia Ortopédica em Fisioterapia

Apostila de formação teórico-prática para estudantes em fase final da graduação e fisioterapeutas.

**[Abrir ou baixar a apostila em PDF](curso/hidroterapia-ortopedica.pdf)**

O material tem 40 páginas e inclui:

- 16 módulos: fundamentos, física, fisiologia, segurança, avaliação, prescrição, métodos e aplicações ortopédicas.
- Biblioteca de exercícios, cinco casos clínicos comentados e quatro oficinas supervisionadas.
- Caderno de aprofundamento, glossário e guia para o docente.
- 12 roteiros de vídeos práticos, ainda não gravados ou vinculados.
- Avaliação com 15 questões, gabarito comentado, rubrica prática e fichas de atendimento.
- Referências, sumário com navegação e ilustrações vetoriais conceituais.

A carga horária de 80 horas é uma proposta pedagógica, sujeita à definição institucional. O conteúdo é uma versão educacional para revisão técnico-científica antes de oferta como curso. A edição de 2026 não representa uma revisão bibliográfica atualizada até essa data. A aplicação clínica exige avaliação individual e supervisão quando pertinente.

## Arquivos

- [PDF](curso/hidroterapia-ortopedica.pdf): material diagramado para leitura.
- [Fonte em Markdown](curso/apostila.md): conteúdo editável.
- [Gerador](scripts/gerar_pdf.py): produz o PDF localmente com ReportLab.
- [Dependência do gerador](scripts/requirements.txt).

## Gerar novamente

Use Python 3 e as fontes DejaVu Sans disponíveis em `/usr/share/fonts/truetype/dejavu` (pacote `fonts-dejavu-core` em Debian/Ubuntu). Não é necessário executar serviços.

```bash
cd /workspace/crimpf
python -m venv --system-site-packages /workspace/.venvs/crimpf-pdf
/workspace/.venvs/crimpf-pdf/bin/python -m pip install -r scripts/requirements.txt
/workspace/.venvs/crimpf-pdf/bin/python scripts/gerar_pdf.py
```

O gerador não acessa a rede. Ele sobrescreve apenas `curso/hidroterapia-ortopedica.pdf`. O PDF mantém texto pesquisável, fontes incorporadas, marcadores e links. Não é um formulário eletrônico preenchível nem um PDF com acessibilidade certificada.

## Vídeos e revisão

Os roteiros estão na apostila. Os vídeos deverão ser gravados, revisados e hospedados; só então devem ser inseridos links e QR codes funcionais. A revisão técnica, a autoria profissional, a identidade institucional e as condições de certificação deverão ser definidas pelo responsável pelo curso.
