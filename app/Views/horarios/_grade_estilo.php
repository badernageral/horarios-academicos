<?php // Estilo COMPARTILHADO da malha de horários.
      // Incluído pela grade por turma e pela grade por sala (ensalamento), para
      // as duas terem exatamente a mesma aparência. O que é específico de cada
      // uma — arrastar, marcações de conflito, impressão — fica na própria view. ?>
<style>
* {
  print-color-adjust: exact !important;
  -webkit-print-color-adjust: exact !important;
  color-adjust: exact !important;
}
.grade-table {
  border-collapse: collapse;
  table-layout: fixed;
  min-width: 700px;
  font-size: 12px;
}
.grade-table th {
  background: #f1f5f9;
  font-size: 12px;
  font-weight: 600;
  text-align: center;
  padding: 7px 6px;
  border: 1px solid #e2e8f0;
  position: sticky;
  top: 0;
  z-index: 3;
  white-space: nowrap;
}
.grade-table th.col-hora {
  text-align: left;
  position: sticky;
  left: 0;
  z-index: 4;
}
.grade-table td.col-hora {
  background: #f8fafc;
  font-size: 11px;
  color: #64748b;
  white-space: nowrap;
  padding: 3px 8px;
  border: 1px solid #e2e8f0;
  position: sticky;
  left: 0;
  z-index: 1;
  vertical-align: middle;
}
/* Faixa escura com o nome da turma (ou da sala, no ensalamento) */
.grade-turma-header td {
  background: #1e293b;
  color: #f1f5f9;
  font-weight: 600;
  font-size: 12px;
  padding: 5px 10px;
  border: 1px solid #334155;
  position: sticky;
  left: 0;
  z-index: 2;
}
/* Toda linha de horário tem a MESMA altura, então um bloco de N aulas fica
   N vezes maior que um de 1 aula. Sem isso a linha se ajusta ao conteúdo e
   uma disciplina de 2 aulas antes do intervalo acaba do mesmo tamanho de
   uma de 1 aula (as duas linhas dividem a altura entre si). */
.grade-table tbody tr.slot-row      { height: 70px; }
.grade-table tbody tr.intervalo-row { height: 24px; }
/* Blocos preenchem a célula inteira, para o tamanho refletir a duração.
   Altura percentual não resolve dentro de <td>, então o bloco é posicionado
   sobre a célula (a faixa do professor volta a ficar rente à base). */
.grade-cell { position: relative; }
.grade-cell > .disc-block {
  position: absolute;
  top: 4px; right: 4px; bottom: 4px; left: 4px;
}
.grade-cell {
  vertical-align: top;
  padding: 3px;
  border: 1px solid #e2e8f0;
  min-width: 130px;
  background: #fff;
  transition: background 0.12s;
}
.disc-block {
  user-select: none;
  padding: 4px 7px 24px;
  border-radius: 5px;
  line-height: 1.35;
  box-sizing: border-box;
  position: relative;
  overflow: hidden;
}
.disc-faixa {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  /* A cor do texto vem inline, por contraste com a secundária do professor
     (ColorHelper::textoSobre). Branco fixo aqui sumia em tons claros. */
  font-size: 10px;
  font-weight: 600;
  padding: 3px 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.disc-nota {
  /* Sem font-size/weight próprios: herda os do .disc-hora (12px/700), para a
     anotação ter o mesmo corpo do horário ao lado. Só o itálico distingue. */
  font-style: italic;
}
.disc-nota:not(:empty)::before { content: ' · '; opacity: .6; }
</style>
