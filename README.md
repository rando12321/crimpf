# Hidroterapia Ortopédica em Fisioterapia

Apostila de formação teórico-prática para estudantes em fase final da graduação e fisioterapeutas.

**[Abrir ou baixar a apostila em PDF](curso/hidroterapia-ortopedica.pdf)**

A edição ampliada e ilustrada tem **72 páginas**, cerca de **18 mil palavras** e inclui:

- 16 módulos: fundamentos, física, fisiologia, segurança, avaliação, prescrição, métodos e aplicações ortopédicas.
- Biblioteca com oito exercícios ilustrados, cinco casos clínicos comentados, casos nos módulos e quatro oficinas supervisionadas.
- Capítulo dedicado ao porquê dos efeitos: propriedade física → resposta imediata → exercício e dose → adaptação → função.
- Anatomia funcional, caderno de aprofundamento, glossário e guia para o docente.
- 12 roteiros de vídeos práticos, ainda não gravados ou vinculados.
- Avaliação com 15 questões e seis questões avançadas, comentários de resposta, rubrica prática e fichas de atendimento.
- Nove pranchas didáticas, cinco esquemas vetoriais, referências, sumário e índice de ilustrações com navegação.
- Explicações de biomecânica, fisiologia, escolha da dose, progressão, regressão e limites da evidência.

A carga horária de 80 horas é uma proposta pedagógica, sujeita à definição institucional. O conteúdo é uma versão educacional para revisão técnico-científica antes de oferta como curso. A edição de 2026 não representa uma revisão bibliográfica atualizada até essa data. A aplicação clínica exige avaliação individual e supervisão quando pertinente.

## Arquivos

- [PDF](curso/hidroterapia-ortopedica.pdf): material diagramado para leitura.
- [Fonte em Markdown](curso/apostila.md): conteúdo editável.
- [Gerador](scripts/gerar_pdf.py): produz o PDF localmente com ReportLab.
- [Ilustrações e nota de origem](curso/imagens/README.md).
- [Dependência do gerador](scripts/requirements.txt).

## Gerar novamente

Use Python 3.9 ou superior e as fontes DejaVu Sans disponíveis em `/usr/share/fonts/truetype/dejavu` (pacote `fonts-dejavu-core` em Debian/Ubuntu). A opção `--compact` utiliza Ghostscript (`gs`, pacote `ghostscript` em Debian/Ubuntu), já disponível no ambiente preparado. Não é necessário executar serviços.

```bash
cd /workspace/crimpf
python -m venv --system-site-packages /workspace/.venvs/crimpf-pdf
/workspace/.venvs/crimpf-pdf/bin/python -m pip install -r scripts/requirements.txt
/workspace/.venvs/crimpf-pdf/bin/python scripts/gerar_pdf.py --compact
```

Sem Ghostscript, omita `--compact` para obter o PDF de maior tamanho. A compactação preserva as dimensões originais das imagens, os links e os marcadores. Os PNG originais permanecem intactos.

O gerador não acessa a rede. Ele sobrescreve apenas `curso/hidroterapia-ortopedica.pdf`. O PDF mantém texto pesquisável, fontes incorporadas, marcadores e links. Não é um formulário eletrônico preenchível nem um PDF com acessibilidade certificada.

## Vídeos e revisão

As pranchas são ilustrações geradas por IA, com legendas e limitações explícitas, e não fotografias de atendimento ou demonstrações clínicas validadas.

Os roteiros estão na apostila. Os vídeos deverão ser gravados, revisados e hospedados; só então devem ser inseridos links e QR codes funcionais. A revisão técnica, a autoria profissional, a identidade institucional e as condições de certificação deverão ser definidas pelo responsável pelo curso.
