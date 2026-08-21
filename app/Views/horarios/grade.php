<?php
$pageTitle = 'Grade de Horários';
$dias = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta'];

$corSemProf = '#94a3b8';
?>

<?php require VIEW_PATH . '/horarios/_grade_estilo.php'; ?>
<style>
.grade-cell.drag-over {
  background: #dbeafe !important;
  outline: 2px dashed #3b82f6;
  outline-offset: -2px;
}
.grade-cell.drag-reject {
  background: #fee2e2 !important;
  outline: 2px dashed #ef4444;
  outline-offset: -2px;
}
<?php // O visual do bloco vem do parcial; aqui só o que é de arrastar. ?>
.disc-block { cursor: grab; transition: box-shadow 0.1s; }
.disc-block:hover { box-shadow: 0 2px 6px rgba(0,0,0,.15); }
.disc-block.dragging { opacity: 0.3; cursor: grabbing; }
/* Enquanto arrasta: onde ESTE professor já dá aula em outras turmas. Soltar
   num horário que colida com um destes gera conflito. `outline` em vez de
   `border` porque o bloco é posicionado em absoluto — borda deslocaria o
   conteúdo; outline com offset negativo desenha por dentro, sem mexer no
   layout. */
.disc-block.prof-ocupado {
  outline: 3px solid #dc2626;
  outline-offset: -3px;
  box-shadow: 0 0 0 2px rgba(220,38,38,.25);
}
/* Célula da PRÓPRIA turma onde soltar colidiria com uma aula que este professor
   já tem em outra turma. É o destaque que importa: fica sob os olhos de quem
   arrasta, enquanto os blocos da outra turma podem estar fora da tela.

   Desenhado como ::after POR CIMA do bloco: como fundo do <td> ele ficava
   escondido atrás das células já ocupadas, que são justamente as que mais
   interessam. Sem z-index de propósito — o ::after é o último filho posicionado
   do <td>, então já pinta depois do .disc-block; um z-index alto passaria por
   cima dos cabeçalhos fixos (z-index 1..4) ao rolar.
   pointer-events:none é obrigatório: sem isso a camada engoliria o drop. */
/* O popover de ajuda tem várias linhas; o padrão do Bootstrap (276px) aperta. */
.popover.popover-ajuda { max-width: 420px; }

/* Anotações da grade (ex.: "Reposição do dia 10/02"). */
/* Fica na MESMA linha do horário: o bloco de 1 aula é baixo demais para uma
   linha extra, e embaixo a anotação ficava cortada. */
.turma-nota {
  font-style: italic;
  font-weight: 400;
  opacity: .85;
}
.turma-nota:not(:empty)::before { content: ' — '; }
.btn-nota {
  background: none;
  border: 0;
  color: inherit;
  opacity: .55;
  padding: 0 4px;
  line-height: 1;
  cursor: pointer;
}
.btn-nota:hover { opacity: 1; }

/* Interruptores do topo: escondem a marcação sem mexer na lógica — as classes
   continuam sendo aplicadas, só não são pintadas. */
body.sem-disp-prof .grade-cell.cell-pref::after,
body.sem-disp-prof .grade-cell.cell-reserva::after { display: none; }
body.sem-lacunas .grade-cell.cell-lacuna {
  background-color: #fff;
  background-image: none;
}

/* Lacuna da turma: turno em que ela não tem aula. Sombreado discreto, para
   distinguir de "vago" — e bem diferente do vermelho de conflito no arraste.
   Fica no fundo da célula: como os blocos são translúcidos (~35%), a hachura
   ainda aparece por baixo de uma aula que porventura esteja ali. */
.grade-cell.cell-lacuna {
  background-color: #f1f5f9;
  background-image: repeating-linear-gradient(45deg,
      rgba(100,116,139,.18), rgba(100,116,139,.18) 5px,
      transparent 5px, transparent 10px);
}

/* Durante o arraste, nas células da turma: verde = turno preferido do
   professor, amarelo = turno "só se precisar". Preenchimento suave, sem
   hachura — a hachura fica reservada para problema (conflito e lacuna). */
.grade-cell.cell-pref::after,
.grade-cell.cell-reserva::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.grade-cell.cell-pref::after {
  background: rgba(22,163,74,.20);
  outline: 2px solid rgba(22,163,74,.65);
  outline-offset: -2px;
}
.grade-cell.cell-reserva::after {
  background: rgba(245,158,11,.22);
  outline: 2px dashed rgba(217,119,6,.75);
  outline-offset: -2px;
}

.grade-cell.cell-conflito::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
  outline: 2px solid #dc2626;
  outline-offset: -2px;
  background: repeating-linear-gradient(45deg,
      rgba(220,38,38,.32), rgba(220,38,38,.32) 6px,
      transparent 6px, transparent 12px);
}
.disc-block * { pointer-events: none; }
.limbo-zone {
  background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 12px, #f1f5f9 12px, #f1f5f9 24px);
  border: 1px dashed #94a3b8;
  padding: 6px;
  transition: background 0.12s;
}
.limbo-zone.drag-over {
  background: #dbeafe !important;
  outline: 2px dashed #3b82f6;
  outline-offset: -2px;
}
.limbo-zone.drag-reject {
  background: #fee2e2 !important;
  outline: 2px dashed #ef4444;
  outline-offset: -2px;
}
.limbo-itens { display: flex; flex-wrap: wrap; gap: 6px; align-items: stretch; }
.limbo-itens .disc-block { min-width: 130px; flex: 0 0 auto; }
.limbo-hint { color: #94a3b8; font-size: 11px; font-style: italic; padding: 4px; }
#toast-container {
  position: fixed; bottom: 1.5rem; right: 1.5rem;
  z-index: 9999; display: flex; flex-direction: column; gap: 8px;
}
<?php if ($filtroAtivo): ?>
/* Filtro ativo: somente visualização */
.disc-block { cursor: default; }
<?php endif; ?>

/* ── Impressão: somente a grade ───────────────────────────────── */
@media print {
  #sidebar, .topbar, .no-print, .limbo-row, #toast-container,
  .modal, .modal-backdrop { display: none !important; }
  /* Uma turma por página. A seleção do #modalImprimir retira do DOM as turmas
     não escolhidas (ver JS), então esta regra só enxerga as que vão imprimir e
     a primeira delas nunca herda quebra — nada de folha em branco. */
  .turma-bloco + .turma-bloco { break-before: page; page-break-before: always; }
  .content-wrapper { padding: 0 !important; }
  #page-content { width: 100% !important; }
  .card { box-shadow: none !important; border: none !important; }
  .card-body { overflow: visible !important; }
  body { background: #fff !important; }
  .grade-table th, .grade-table th.col-hora, .grade-table td.col-hora,
  .grade-turma-header td { position: static !important; }
  .disc-block { cursor: default; }
  /* Compacta a grade no papel. Sem isso a turma ocupa quase exatamente a
     altura da folha, e qualquer ajuste de fonte do navegador (tamanho
     mínimo, "ignorar fontes da página", escala) empurra o rodapé da
     tabela para uma segunda folha praticamente vazia. */
  .grade-table th          { padding: 2px 4px !important; }
  .grade-table td.col-hora { padding: 1px 6px !important; }
  .grade-turma-header td   { padding: 2px 8px !important; }
  .grade-cell              { padding: 0 !important; }
  .disc-block              { padding: 2px 4px 12px !important; line-height: 1.15 !important; }
  .disc-faixa              { font-size: 9px !important; padding: 1px 4px !important; }
  /* Folga de ~15% em altura E largura: impressoras reais impõem margens
     mínimas maiores que os 8mm do @page, e configurações salvas (escala,
     retrato) encolhem a área útil — sem folga, a turma mais alta transborda
     e o cabeçalho repetido vira uma "folha em branco". zoom afeta o layout
     (Firefox 126+/Chrome), então a paginação enxerga o tamanho reduzido. */
  .grade-table { zoom: 0.85; }
  /* Linhas uniformes também no papel (proporção mantida), porém menores para
     a turma continuar cabendo com folga em uma folha. */
  .grade-table tbody tr.slot-row      { height: 50px !important; }
  .grade-table tbody tr.intervalo-row { height: 12px !important; }
  .intervalo-row td { padding: 0 !important; font-size: 8px !important; line-height: 1.1 !important; }
  /* Cada linha traz nome + horário + professor, então a tipografia do bloco
     encolhe para caber na altura de UMA linha (o !important vence os
     font-size inline da marcação). */
  .grade-cell > .disc-block { top: 2px; right: 2px; bottom: 2px; left: 2px; }
  .disc-block        { padding: 1px 4px 9px !important; }
  .disc-block > div  { font-size: 10px !important; line-height: 1.15 !important; }
  .disc-faixa        { font-size: 8px !important; padding: 0 3px !important; }
  /* Retrato: a folha é ~30% mais estreita e as 5 colunas de dias passariam
     da margem direita — precisa encolher mais que a paisagem. */
  html.print-retrato .grade-table { zoom: 0.75; }
}
</style>

<!-- Regra de página isolada: @page não pode ser trocada por classe, então o
     seletor de orientação do #modalImprimir reescreve este bloco. -->
<style id="regra-pagina">@page { size: landscape; margin: 8mm; }</style>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap no-print">
  <a href="<?= $base ?>/horarios" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-semibold"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Grade de Horários</h5>
  <span class="badge bg-secondary"><?= htmlspecialchars($geracao['descricao'] ?? '') ?></span>

  <?php // Alterna a MESMA geração entre a grade por turma e a grade por sala. ?>
  <div class="form-check form-switch mb-0 ms-2"
       title="Mostra a mesma geração organizada por sala, para conferir o ensalamento">
    <input class="form-check-input" type="checkbox" id="chkEnsalamento">
    <label class="form-check-label small" for="chkEnsalamento">
      <i class="bi bi-door-open me-1"></i>Visualizar ensalamento
    </label>
  </div>

  <?php // ms-auto passou para cá: o badge saiu da direita, os botões continuam nela. ?>
  <div class="dropdown ms-auto">
    <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown">
      <i class="bi bi-box-arrow-up me-1"></i>Exportar
    </button>
    <ul class="dropdown-menu">
      <li><h6 class="dropdown-header">Escopo</h6></li>
      <li>
        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalImprimir" data-escopo="turma">
          <i class="bi bi-grid-3x3-gap me-2"></i>Grade por turma
        </a>
      </li>
      <li>
        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalImprimir" data-escopo="professor">
          <i class="bi bi-person-badge me-2"></i>Grade por professor
        </a>
      </li>
      <li>
        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalImprimir" data-escopo="sala">
          <i class="bi bi-door-open me-2"></i>Grade por sala
        </a>
      </li>
    </ul>
  </div>

  <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalConflitos">
    <i class="bi bi-exclamation-triangle me-1"></i>Verificar Conflitos
  </button>
  <?php if (!empty($semestreId)): ?>
  <form method="POST" action="<?= $base ?>/horarios/<?= $semestreId ?>/gerar" class="mb-0"
        onsubmit="return confirm('Regerar o horário descarta esta geração, incluindo ajustes manuais e itens no limbo. Continuar?');">
    <button type="submit" class="btn btn-sm btn-success">
      <i class="bi bi-arrow-repeat me-1"></i>Regerar
    </button>
  </form>
  <?php endif; ?>
</div>
<?php if (!empty($desatualizadas)): ?>
<?php // A grade é um retrato do momento da geração: horarios.turma_id é gravado
      // junto. Trocar a turma da disciplina depois NÃO mexe aqui — daí o aviso. ?>
<div class="alert alert-warning d-flex align-items-start gap-2">
  <i class="bi bi-exclamation-triangle-fill fs-5"></i>
  <div class="flex-grow-1">
    <div class="fw-semibold">Esta grade está desatualizada</div>
    <div class="small">
      <?= count($desatualizadas) ?> disciplina(s) mudaram de turma depois desta geração.
      As aulas abaixo continuam na turma antiga:
    </div>
    <ul class="small mb-2 mt-1">
      <?php foreach ($desatualizadas as $d): ?>
      <li>
        <strong><?= htmlspecialchars($d['disciplina']) ?></strong>:
        <?= $d['aulas'] ?> aula(s) ainda em <em><?= htmlspecialchars($d['antiga']) ?></em>,
        mas a disciplina agora é de <em><?= htmlspecialchars($d['nova']) ?></em>.
      </li>
      <?php endforeach; ?>
    </ul>
    <div class="small text-muted mb-2">
      Nada foi alterado automaticamente. Regerar substitui a grade inteira desta
      geração — inclusive os ajustes manuais feitos por arrastar e soltar.
    </div>
    <?php if (!empty($semestreId)): ?>
    <?php // Regerar exige clique + confirmação: nada acontece sozinho. ?>
    <form method="POST" action="<?= $base ?>/horarios/<?= $semestreId ?>/gerar" class="mb-0"
          onsubmit="return confirm('Regerar descarta esta geração, incluindo ajustes manuais e itens no limbo. Continuar?');">
      <button type="submit" class="btn btn-sm btn-warning">
        <i class="bi bi-arrow-repeat me-1"></i>Regerar agora
      </button>
    </form>
    <?php else: ?>
    <a href="<?= $base ?>/horarios" class="btn btn-sm btn-warning">
      <i class="bi bi-arrow-repeat me-1"></i>Ir para regerar
    </a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Modal: escolher turmas para imprimir ou exportar em PDF (mesmas opções) -->
<div class="modal fade" id="modalImprimir" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">
          <i class="bi bi-box-arrow-up me-2"></i><span id="exp-titulo">Exportar grade</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small mb-1" for="imprimir-orientacao">Orientação do papel</label>
          <select id="imprimir-orientacao" class="form-select form-select-sm">
            <option value="landscape" selected>Paisagem (deitado)</option>
            <option value="portrait">Retrato (em pé)</option>
          </select>
        </div>

        <?php // Seleção de turmas: só aparece no escopo turma. ?>
        <div id="exp-turmas">
          <?php if (empty($grade)): ?>
          <p class="text-muted mb-0">Nenhuma turma nesta grade.</p>
          <?php else: ?>
          <p class="small text-muted">Cada turma marcada sai em uma página.</p>
          <div class="form-check border-bottom pb-2 mb-2">
            <input class="form-check-input" type="checkbox" id="imprimir-todas" checked>
            <label class="form-check-label fw-semibold" for="imprimir-todas">Todas as turmas</label>
          </div>
          <?php foreach ($grade as $turmaId => $row): ?>
          <div class="form-check">
            <input class="form-check-input imprimir-turma" type="checkbox"
                   id="imprimir-turma-<?= $turmaId ?>" value="<?= $turmaId ?>" checked>
            <label class="form-check-label" for="imprimir-turma-<?= $turmaId ?>">
              <?= htmlspecialchars($row['curso_nome']) ?> — <strong><?= htmlspecialchars($row['turma_nome']) ?></strong>
            </label>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <p class="small text-muted mb-0" id="exp-aviso-tudo" style="display:none">
          Sai a agenda completa desta geração — um por página, com todas as aulas.
        </p>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-danger exp-formato"  data-formato="pdf">
            <i class="bi bi-filetype-pdf me-1"></i>PDF
          </button>
          <button type="button" class="btn btn-outline-primary exp-formato" data-formato="png">
            <i class="bi bi-image me-1"></i>PNG
          </button>
          <button type="button" class="btn btn-dark exp-formato" data-formato="imprimir">
            <i class="bi bi-printer me-1"></i>Imprimir
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAnotacao" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-pencil me-2"></i><span id="nota-titulo">Anotação</span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small" for="nota-texto">Texto da anotação</label>
        <textarea id="nota-texto" class="form-control" rows="2" maxlength="200"
                  placeholder="Ex.: Reposição do dia 10/02"></textarea>
        <div class="form-text">Aparece na grade, na impressão e no PDF. Máx. 200 caracteres.</div>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-outline-danger" id="nota-remover">
          <i class="bi bi-trash me-1"></i>Remover
        </button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="nota-salvar">
            <i class="bi bi-check-lg me-1"></i>Salvar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: verificar conflitos entre disciplinas -->
<div class="modal fade" id="modalConflitos" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">
          <i class="bi bi-exclamation-triangle me-2 text-warning"></i>Verificar Conflitos de Horário
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">
          Informe o nome das disciplinas que o aluno pretende cursar, <strong>uma por linha</strong>.
          O sistema verifica se há sobreposição de horários nesta geração.
        </p>
        <textarea id="conflitos-input" class="form-control font-monospace" rows="8"
                  placeholder="Matemática I&#10;Física Geral&#10;Cálculo Diferencial e Integral"></textarea>
        <div id="conflitos-resultado" class="mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-warning" id="conflitos-verificar">
          <i class="bi bi-search me-1"></i>Verificar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Filtros de visualização -->
<form method="GET" action="<?= $base ?>/horarios/geracao/<?= $geracaoId ?>/grade" class="row g-2 mb-3 align-items-end no-print">
  <div class="col-md">
    <label class="form-label small mb-1">Curso</label>
    <select name="curso_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">— Todos —</option>
      <?php foreach ($cursosFiltro as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $cursoFiltro == $c['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['nome']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md">
    <label class="form-label small mb-1">Turma</label>
    <select name="turma_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">— Todas —</option>
      <?php foreach ($turmasFiltro as $t): ?>
      <option value="<?= $t['id'] ?>" <?= $turmaFiltro == $t['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($t['curso_nome'] . ' – ' . $t['serie_periodo']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md">
    <label class="form-label small mb-1">Professor</label>
    <select name="professor_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">— Todos —</option>
      <?php foreach ($professoresFiltro as $p): ?>
      <option value="<?= $p['id'] ?>" <?= $profFiltro == $p['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($p['nome']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md">
    <label class="form-label small mb-1">Sala</label>
    <select name="sala_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">— Todas —</option>
      <?php foreach ($salasFiltro as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $salaFiltro == $s['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($s['nome']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-auto d-flex gap-2">
    <?php if ($filtroAtivo): ?>
    <a href="<?= $base ?>/horarios/geracao/<?= $geracaoId ?>/grade" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-x-lg me-1"></i>Limpar filtros
    </a>
    <span class="badge bg-info text-dark align-self-center">
      <i class="bi bi-eye me-1"></i>Somente visualização
    </span>
    <?php endif; ?>
  </div>
</form>

<!-- Qualidade da geração -->
<?php if (!empty($qualidade['professores'])): ?>
<div class="mb-3 no-print d-flex align-items-center gap-3 flex-wrap">
  <button class="btn btn-sm btn-outline-info" data-bs-toggle="collapse" data-bs-target="#painelQualidade">
    <i class="bi bi-speedometer2 me-1"></i>Relatório da grade atual
    <span class="badge bg-secondary ms-1"><?= $qualidade['total_aulas'] ?> aulas</span>
    <?php if ($qualidade['no_limbo'] > 0): ?>
    <span class="badge bg-danger ms-1"><?= $qualidade['no_limbo'] ?> no limbo</span>
    <?php endif; ?>
    <span class="badge bg-info text-dark ms-1">média <?= $qualidade['media_dias'] ?> dia(s)/prof · máx <?= $qualidade['max_dias'] ?></span>
    <?php if ($qualidade['com_buraco'] > 0): ?>
    <span class="badge bg-warning text-dark ms-1"><?= $qualidade['com_buraco'] ?> prof. com semana espalhada</span>
    <?php else: ?>
    <span class="badge bg-success ms-1">semanas compactas</span>
    <?php endif; ?>
  </button>

  <?php // Sempre marcados ao abrir a página: não persistem entre recargas. ?>
  <div class="form-check form-switch mb-0"
       title="Durante o arraste, pinta os turnos do professor: verde = preferido, amarelo = só se precisar">
    <input class="form-check-input" type="checkbox" id="chkDispProf" checked>
    <label class="form-check-label small" for="chkDispProf">Disponibilidade semanal do professor</label>
  </div>
  <div class="form-check form-switch mb-0" title="Sombreia os turnos em que a turma não tem aula">
    <input class="form-check-input" type="checkbox" id="chkLacunas" checked>
    <label class="form-check-label small" for="chkLacunas">Turnos sem aula</label>
  </div>

  <?php // Liberação para /publico: só aparece com a geração aberta sem filtro. ?>
  <div class="form-check form-switch mb-0"
       title="Quando ligado, este horário fica visível em /publico para alunos e professores">
    <input class="form-check-input" type="checkbox" id="chkPublico"
           <?= !empty($geracao['publico']) ? 'checked' : '' ?>>
    <label class="form-check-label small" for="chkPublico">
      <i class="bi bi-globe me-1"></i>Liberado ao público
    </label>
  </div>

  <button type="button" class="btn btn-sm btn-outline-secondary no-print" id="btnAjuda"
          aria-label="Como usar a grade">
    <i class="bi bi-question-circle"></i>
  </button>

  <?php // Conteúdo do popover de ajuda (escondido; o JS o injeta no popover). ?>
  <div id="ajuda-conteudo" class="d-none">
    <ul class="mb-0 ps-3 small">
      <li><strong>Mover:</strong> arraste o bloco para outro dia ou horário. Soltar sobre um
          bloco ocupado <em>troca</em> os dois de lugar.</li>
      <li>Não é possível mover entre turmas diferentes.</li>
      <li><strong>Anotar:</strong> dê <strong>duplo clique</strong> no bloco da disciplina.
          Para anotar a turma, use o lápis ao lado do nome dela. Salvar com o texto vazio remove
          a anotação.</li>
      <li><strong>Enquanto arrasta</strong>, as células da turma indicam o professor:
          <span class="text-success">verde</span> = turno preferido dele,
          <span class="text-warning">amarelo</span> = só se precisar,
          <span class="text-danger">vermelho</span> = ele já tem aula nesse horário em outra turma.</li>
      <li><strong>Hachura cinza:</strong> turno em que a turma não tem aula. O gerador não usa,
          mas você pode soltar algo ali — é onde cabem as reposições.</li>
      <li><strong>Limbo:</strong> a faixa ao pé de cada turma guarda as disciplinas sem horário.
          Arraste de lá para a grade, ou para lá para tirar da grade.</li>
      <li><strong>Desfazer:</strong> logo após mover, aparece um aviso no canto com o botão
          <em>Desfazer</em>.</li>
      <li>Com um <strong>filtro ativo</strong>, a grade fica somente leitura.</li>
      <li><strong>Visualizar ensalamento:</strong> troca para a mesma geração organizada
          por sala, para conferir onde cada aula acontece. Essa visão é só de leitura.</li>
      <li><strong>Liberado ao público:</strong> publica este horário em
          <code>/publico</code>, onde alunos e professores consultam sem login.
          Só aparece lá se for do semestre corrente. Vem desligado, e
          <strong>regerar desliga de novo</strong> — a grade nova precisa ser
          liberada outra vez.</li>
    </ul>
  </div>

</div>

<div class="no-print">
  <div class="collapse mt-2" id="painelQualidade">
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" style="font-size:12px">
          <thead class="table-light">
            <tr>
              <th>Professor</th>
              <th>Dias de aula</th>
              <th class="text-center">Qtd. dias</th>
              <th class="text-center">Buracos na semana</th>
              <th class="text-center">Carga semanal (aulas)</th>
              <th class="text-center">Carga relógio</th>
            </tr>
          </thead>
          <tbody>
          <?php $abrev = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb']; ?>
          <?php foreach ($qualidade['professores'] as $q): ?>
            <tr>
              <td><?= htmlspecialchars($q['nome']) ?></td>
              <td><?= implode(', ', array_map(fn($d) => $abrev[$d] ?? $d, $q['dias'])) ?></td>
              <td class="text-center">
                <?php
                  // 1 dia só viola o mínimo quando o professor tem 2+ aulas
                  $nDias = count($q['dias']);
                  $badgeDias = ($nDias === 1 && $q['aulas'] >= 2) ? 'bg-danger'
                             : ($nDias <= 2 ? 'bg-success'
                             : ($nDias === 3 ? 'bg-info text-dark' : 'bg-secondary'));
                ?>
                <span class="badge <?= $badgeDias ?>" <?= $badgeDias === 'bg-danger' ? 'title="Abaixo do mínimo de 2 dias"' : '' ?>>
                  <?= $nDias ?>
                </span>
              </td>
              <td class="text-center">
                <?php if ($q['buracos'] > 0): ?>
                <span class="badge bg-warning text-dark"><?= $q['buracos'] ?> dia(s)</span>
                <?php else: ?>
                <span class="text-success"><i class="bi bi-check-lg"></i></span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?= (int)($q['periodos'] ?? 0) ?> aula<?= (int)($q['periodos'] ?? 0) === 1 ? '' : 's' ?>
                <?php if (!empty($q['ead'])): ?>
                <span class="badge bg-light text-secondary border ms-1" title="Aulas EaD incluídas na carga"><?= (int)$q['ead'] ?> EaD</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= \App\Services\TimeHelper::formatDuration($q['minutos']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (empty($grade)): ?>
<div class="alert alert-info">Nenhum horário encontrado para esta geração.</div>
<?php else: ?>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0 overflow-auto" id="area-grade">
      <?php foreach ($grade as $turmaId => $row): ?>

    <!-- Uma tabela por turma (na impressão, uma turma por página) -->
    <div class="turma-bloco" data-turma-id="<?= $turmaId ?>">
    <table class="grade-table w-100">
      <colgroup>
        <col style="width:90px">
        <col><col><col><col><col>
      </colgroup>
      <thead>
        <!-- Cabeçalho da turma -->
        <tr class="grade-turma-header">
          <td colspan="6">
            <?= htmlspecialchars($row['curso_nome']) ?> — <?= htmlspecialchars($row['turma_nome']) ?>
            <span class="turma-nota" data-turma-id="<?= $turmaId ?>"><?php
              $notaTurma = $anotacoesTurma[$turmaId] ?? '';
              echo $notaTurma !== '' ? htmlspecialchars($notaTurma) : '';
            ?></span>
            <?php if (!$filtroAtivo): ?>
            <button type="button" class="btn-nota no-print" data-nota-tipo="turma"
                    data-nota-id="<?= $turmaId ?>" title="Anotação da turma">
              <i class="bi bi-pencil"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th class="col-hora">Hora</th>
          <?php foreach ($dias as $dNome): ?>
          <th><?= $dNome ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>

        <!-- Linhas de slots de tempo -->
        <?php
          // Lacuna = a turma não tem aula naquele turno. Só sombreamento: a
          // restrição de verdade já foi aplicada na geração. Uma célula que
          // atravessa turnos é lacuna se QUALQUER turno tocado estiver bloqueado.
          $ehLacuna = function (int $turmaId, int $dia, int $ini, int $fim) use ($turnosMin, $turmaLiberado): bool {
              if (empty($turmaLiberado[$turmaId])) return false;   // turma sem cadastro: não sombreia
              foreach ($turnosMin as $chave => $t) {
                  if ($ini >= $t['fim'] || $fim <= $t['inicio']) continue;
                  if (empty($turmaLiberado[$turmaId][$dia][$chave])) return true;
              }
              return false;
          };
        ?>
        <?php foreach ($row['slots'] as $slotIdx => $slot): ?>
        <?php if ($slot['type'] === 'intervalo'): ?>

        <!-- Linha de intervalo -->
        <tr class="intervalo-row">
          <td class="col-hora" style="background:#fef9c3;color:#92400e;font-style:italic;font-size:10px;">
            <?= \App\Services\TimeHelper::fromMinutes($slot['min']) ?>–<?= \App\Services\TimeHelper::fromMinutes($slot['fim']) ?>
          </td>
          <td colspan="5" style="background:#fef9c3;border:1px solid #fde68a;text-align:center;font-size:10px;color:#92400e;font-style:italic;padding:2px;">
            <i class="bi bi-cup-hot" style="font-size:10px;"></i> Intervalo
          </td>
        </tr>

        <?php else: /* slot de aula */ ?>
        <?php $slotMin = $slot['min']; $slotFimMin = $slot['fim']; ?>
        <tr class="slot-row" data-turma-id="<?= $turmaId ?>">

          <!-- Rótulo de hora -->
          <td class="col-hora">
            <?= \App\Services\TimeHelper::fromMinutes($slotMin) ?>–<?= \App\Services\TimeHelper::fromMinutes($slotFimMin) ?>
          </td>

          <!-- Célula por dia -->
          <?php foreach ($dias as $dNum => $dNome): ?>
          <?php if ($row['skip'][$dNum][$slotIdx]): ?>
            <?php /* célula absorvida por rowspan acima — não renderizar */ ?>
          <?php elseif ($row['grid'][$dNum][$slotIdx] !== null): ?>
            <?php
              $h      = $row['grid'][$dNum][$slotIdx];
              $cor    = !empty($h['professor_cor']) ? $h['professor_cor'] : $corSemProf;
              $corSec = !empty($h['professor_cor_secundaria']) ? $h['professor_cor_secundaria'] : $cor;
              $bg     = $cor . '59'; // cor primária com ~35% de opacidade
              // Texto por contraste: o corpo do bloco é medido com a MESMA
              // opacidade do fundo (35% sobre branco); a faixa usa a secundária
              // cheia, onde branco fixo ficava ilegível em tons claros.
              $txt    = \App\Services\ColorHelper::textoSobre($cor, 0.35);
              $txtSec = \App\Services\ColorHelper::textoSobre($corSec);
              $span   = $h['rowspan'];
            ?>
            <td class="grade-cell p-1<?= $ehLacuna($turmaId, $dNum, $slotMin, $slotFimMin) ? ' cell-lacuna' : '' ?>"
                data-dia="<?= $dNum ?>"
                data-turma-id="<?= $turmaId ?>"
                data-slot="<?= $slotIdx ?>"
                data-hora-inicio="<?= substr($h['hora_inicio'],0,5) ?>"
                rowspan="<?= $span ?>"
                style="background:<?= $bg ?>;border-left:4px solid <?= $cor ?>">
              <div class="disc-block"
                   draggable="<?= $filtroAtivo ? 'false' : 'true' ?>"
                   data-horario-id="<?= $h['id'] ?>"
                   data-dia="<?= $dNum ?>"
                   data-slot="<?= $slotIdx ?>"
                   data-rowspan="<?= $span ?>"
                   data-turma-id="<?= $turmaId ?>"
                   data-professor-id="<?= (int)($h['professor_id'] ?? 0) ?>"
                   data-dur-min="<?= \App\Services\TimeHelper::toMinutes($h['hora_fim']) - \App\Services\TimeHelper::toMinutes($h['hora_inicio']) ?>">
                <div style="color:<?= $txt ?>;font-weight:700;font-size:12px">
                  <?= htmlspecialchars($h['disciplina_sigla'] ?: substr($h['disciplina_nome'], 0, 14)) ?>
                </div>
                <div class="disc-hora" style="color:<?= $txt ?>;font-weight:700;font-size:12px">
                  <?= \App\Services\TimeHelper::fromMinutes($h['slot_ini']) ?>–<?= \App\Services\TimeHelper::fromMinutes($h['slot_fim']) ?><span
                    class="disc-nota"><?= !empty($h['observacao']) ? htmlspecialchars($h['observacao']) : '' ?></span>
                </div>
                <?php if ($h['professor_nome']): ?>
                <div class="disc-faixa" style="background:<?= $corSec ?>;color:<?= $txtSec ?>">
                  <?= htmlspecialchars(substr($h['professor_nome'], 0, 26)) ?>
                </div>
                <?php else: ?>
                <div style="position:absolute;bottom:0;left:0;right:0;height:5px;background:<?= $corSec ?>"></div>
                <?php endif; ?>
              </div>
            </td>
          <?php else: ?>
            <td class="grade-cell<?= $ehLacuna($turmaId, $dNum, $slotMin, $slotFimMin) ? ' cell-lacuna' : '' ?>"
                data-dia="<?= $dNum ?>"
                data-turma-id="<?= $turmaId ?>"
                data-slot="<?= $slotIdx ?>"
                data-hora-inicio="<?= \App\Services\TimeHelper::fromMinutes($slotMin) ?>">
            </td>
          <?php endif; ?>
          <?php endforeach; ?>

        </tr>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Limbo da turma: disciplinas sem horário atribuído -->
        <tr class="limbo-row">
          <td class="col-hora" style="background:#f1f5f9;color:#64748b;font-size:10px;font-weight:600;vertical-align:middle;">
            <i class="bi bi-inbox"></i> Limbo
          </td>
          <td colspan="5" class="limbo-zone" data-turma-id="<?= $turmaId ?>">
            <div class="limbo-itens">
              <?php foreach ($row['limbo'] as $h):
                $cor    = !empty($h['professor_cor']) ? $h['professor_cor'] : $corSemProf;
                $corSec = !empty($h['professor_cor_secundaria']) ? $h['professor_cor_secundaria'] : $cor;
                $txt    = \App\Services\ColorHelper::textoSobre($cor, 0.35);
                $txtSec = \App\Services\ColorHelper::textoSobre($corSec);
              ?>
              <div class="disc-block"
                   draggable="<?= $filtroAtivo ? 'false' : 'true' ?>"
                   data-horario-id="<?= $h['id'] ?>"
                   data-turma-id="<?= $turmaId ?>"
                   data-professor-id="<?= (int)($h['professor_id'] ?? 0) ?>"
                   data-dur-min="<?= \App\Services\TimeHelper::toMinutes($h['hora_fim']) - \App\Services\TimeHelper::toMinutes($h['hora_inicio']) ?>"
                   style="background:<?= $cor ?>59">
                <div class="disc-hora" style="color:<?= $txt ?>;font-weight:700;font-size:12px">
                  <?= htmlspecialchars($h['disciplina_sigla'] ?: substr($h['disciplina_nome'], 0, 14)) ?><span
                    class="disc-nota"><?= !empty($h['observacao']) ? htmlspecialchars($h['observacao']) : '' ?></span>
                </div>
                <?php if ($h['professor_nome']): ?>
                <div class="disc-faixa" style="background:<?= $corSec ?>;color:<?= $txtSec ?>">
                  <?= htmlspecialchars(substr($h['professor_nome'], 0, 26)) ?>
                </div>
                <?php else: ?>
                <div style="position:absolute;bottom:0;left:0;right:0;height:5px;background:<?= $corSec ?>"></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              <?php if (empty($row['limbo'])): ?>
              <span class="limbo-hint">Arraste disciplinas para cá para retirá-las temporariamente da grade</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>

      </tbody>
    </table>
    </div><!-- /.turma-bloco -->

      <?php endforeach; ?>
  </div>
</div>

<?php endif; ?>

<div id="toast-container"></div>

<?php // Utilitários compartilhados. Precisam ficar FORA do bloco de drag & drop:
     // aquele só é renderizado sem filtro, e no escopo global — o showToast
     // estava dentro do IIFE, invisível para o segundo <script> da página. ?>
<script>
const base = '<?= $base ?>';

function showToast(msg, type) {
  const c = document.getElementById('toast-container');
  if (!c) return;
  const d = document.createElement('div');
  d.className = 'alert alert-' + type + ' py-2 px-3 mb-0 shadow-sm';
  d.style.fontSize = '13px';
  d.textContent = msg;
  c.appendChild(d);
  setTimeout(() => d.remove(), 3500);
}
</script>

<?php if (!$filtroAtivo): /* drag & drop desabilitado quando há filtro */ ?>
<?php // Usado pela exportação em PNG: rasterizar no servidor exigiria imagick,
     // que o PHP embutido do app desktop não tem. ?>
<script src="<?= $base ?>/assets/vendor/html2canvas/html2canvas.min.js"></script>
<script>
(function () {
  let draggingEl    = null;
  let draggingTurma = null;
  let draggingLimbo = false;

  function guardarDesfazer(acao) {
    sessionStorage.setItem('sgaUndo', JSON.stringify({ ...acao, ts: Date.now() }));
  }

  function isValidTarget(cell) {
    if (cell.dataset.turmaId !== draggingTurma) return false;
    const ocupante = cell.querySelector('.disc-block');
    if (!ocupante) return true;
    // Célula ocupada: vale como troca (swap), exceto vindo do limbo
    return !draggingLimbo && ocupante !== draggingEl;
  }

  // Destaca onde o professor do bloco arrastado já tem aula em OUTRAS turmas:
  // são esses os horários que gerariam conflito de professor ao soltar. Blocos
  // no limbo ficam de fora — não têm horário, então não colidem com nada.
  const OCUPACAO_PROF  = <?= json_encode($ocupacaoProf ?? [], JSON_UNESCAPED_UNICODE) ?>;
  const DISP_PROF      = <?= json_encode($dispProfessor ?? [], JSON_UNESCAPED_UNICODE) ?>;
  const TURNOS_MIN     = <?= json_encode($turnosMin ?? [], JSON_UNESCAPED_UNICODE) ?>;

  function minutos(hhmm) {
    const [h, m] = String(hhmm).split(':');
    return (parseInt(h, 10) * 60) + parseInt(m, 10);
  }

  function marcarOcupacaoProfessor(el) {
    const prof = parseInt(el.dataset.professorId, 10);
    if (!prof) return;
    const turma = el.dataset.turmaId;

    // 1. Os blocos do professor em outras turmas.
    document.querySelectorAll('.disc-block[data-professor-id="' + prof + '"]').forEach(outro => {
      if (outro === el) return;
      if (outro.dataset.turmaId === turma) return;   // mesma turma: já é visível na linha
      if (outro.closest('.limbo-zone')) return;      // sem horário atribuído
      outro.classList.add('prof-ocupado');
    });

    // 2. As células DESTA turma onde soltar colidiria. Só a própria turma
    //    importa: mover entre turmas já é recusado no drop.
    const dur = parseInt(el.dataset.durMin, 10) || 50;
    const ocupado = OCUPACAO_PROF.filter(o =>
      o.prof === prof && String(o.turma) !== String(turma)
    );

    document.querySelectorAll('.grade-cell[data-hora-inicio]').forEach(cell => {
      if (cell.dataset.turmaId !== turma) return;
      const dia = parseInt(cell.dataset.dia, 10);
      const ini = minutos(cell.dataset.horaInicio);
      const fim = ini + dur;

      // Conflito manda em tudo: se o professor já tem aula nesse horário em
      // outra turma, não importa que o turno seja preferido dele.
      if (ocupado.some(o => o.dia === dia && ini < o.fim && fim > o.ini)) {
        cell.classList.add('cell-conflito');
        return;
      }

      // Lacuna da turma TAMBÉM é marcada: elas são usadas para reposição, e a
      // grade pode ser ajustada à mão em caráter excepcional. O gerador segue
      // sem agendar ali sozinho; aqui é só informar se o professor poderia.
      const estado = estadoProfessor(prof, dia, ini, fim);
      if (estado === 1) cell.classList.add('cell-pref');
      else if (estado === 2) cell.classList.add('cell-reserva');
    });
  }

  // Estado do professor para a faixa [ini,fim): null = indisponível,
  // 1 = preferido, 2 = "só se precisar". Basta um turno amarelo entre os
  // atravessados para a faixa inteira valer como amarela — mesma regra do
  // gerador (ScheduleGenerator::preferenciaProfessor).
  function estadoProfessor(prof, dia, ini, fim) {
    const doDia = (DISP_PROF[prof] || {})[dia];
    if (!doDia) return null;

    let estado = 1, tocou = false;
    for (const [chave, t] of Object.entries(TURNOS_MIN)) {
      if (ini >= t.fim || fim <= t.inicio) continue;
      const e = doDia[chave];
      if (!e) return null;               // turno bloqueado para o professor
      if (e === 2) estado = 2;
      tocou = true;
    }
    return tocou ? estado : null;
  }

  function limparOcupacaoProfessor() {
    document.querySelectorAll('.disc-block.prof-ocupado')
            .forEach(b => b.classList.remove('prof-ocupado'));
    document.querySelectorAll('.grade-cell.cell-conflito, .grade-cell.cell-pref, .grade-cell.cell-reserva')
            .forEach(c => c.classList.remove('cell-conflito', 'cell-pref', 'cell-reserva'));
  }

  document.querySelectorAll('.disc-block').forEach(el => {
    el.addEventListener('dragstart', e => {
      draggingEl    = el;
      draggingTurma = el.dataset.turmaId;
      draggingLimbo = !!el.closest('.limbo-zone');
      el.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      marcarOcupacaoProfessor(el);
    });
    el.addEventListener('dragend', () => {
      el.classList.remove('dragging');
      draggingEl = draggingTurma = null;
      draggingLimbo = false;
      limparOcupacaoProfessor();
    });
  });

  document.querySelectorAll('.grade-cell').forEach(cell => {
    cell.addEventListener('dragenter', e => {
      if (!draggingEl) return;
      e.preventDefault();
      cell.classList.remove('drag-over', 'drag-reject');
      cell.classList.add(isValidTarget(cell) ? 'drag-over' : 'drag-reject');
    });

    cell.addEventListener('dragover', e => {
      if (!draggingEl) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
    });

    cell.addEventListener('dragleave', () => {
      cell.classList.remove('drag-over', 'drag-reject');
    });

    cell.addEventListener('drop', e => {
      e.preventDefault();
      cell.classList.remove('drag-over', 'drag-reject');
      if (!draggingEl) return;

      if (cell.dataset.turmaId !== draggingTurma) {
        showToast('Não é possível mover entre turmas diferentes.', 'warning');
        return;
      }

      const el       = draggingEl;
      const ocupante = cell.querySelector('.disc-block');

      // ── Troca (swap): soltou sobre célula ocupada da mesma turma ──
      if (ocupante) {
        if (ocupante === el) return;
        if (draggingLimbo) {
          showToast('Já existe uma disciplina neste horário. Solte numa célula vazia.', 'warning');
          return;
        }
        const a = parseInt(el.dataset.horarioId);
        const b = parseInt(ocupante.dataset.horarioId);
        fetch(base + '/horarios/geracao/trocar', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ horario_a: a, horario_b: b })
        })
        .then(r => r.json())
        .then(data => {
          if (data.ok) {
            guardarDesfazer({ tipo: 'trocar', a, b });
            location.reload();
          } else {
            showToast(data.erro || 'Erro ao trocar.', 'danger');
          }
        })
        .catch(() => showToast('Erro de comunicação.', 'danger'));
        return;
      }

      // ── Mover para célula vazia ──
      const horarioId      = parseInt(el.dataset.horarioId);
      const novoDia        = parseInt(cell.dataset.dia);
      const novaHoraInicio = cell.dataset.horaInicio;

      fetch(base + '/horarios/geracao/mover', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          horario_id:       horarioId,
          novo_dia:         novoDia,
          nova_hora_inicio: novaHoraInicio
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          if (data.anterior) guardarDesfazer({ tipo: 'mover', horario_id: horarioId, anterior: data.anterior });
          location.reload();
        } else {
          showToast(data.erro || 'Erro ao mover.', 'danger');
        }
      })
      .catch(() => showToast('Erro de comunicação.', 'danger'));
    });
  });

  // ── Zona de limbo: disciplinas sem horário atribuído ──────────
  document.querySelectorAll('.limbo-zone').forEach(zone => {
    zone.addEventListener('dragenter', e => {
      if (!draggingEl) return;
      e.preventDefault();
      zone.classList.remove('drag-over', 'drag-reject');
      zone.classList.add(zone.dataset.turmaId === draggingTurma ? 'drag-over' : 'drag-reject');
    });

    zone.addEventListener('dragover', e => {
      if (!draggingEl) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
    });

    zone.addEventListener('dragleave', () => {
      zone.classList.remove('drag-over', 'drag-reject');
    });

    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('drag-over', 'drag-reject');
      if (!draggingEl) return;

      if (zone.dataset.turmaId !== draggingTurma) {
        showToast('Não é possível mover entre turmas diferentes.', 'warning');
        return;
      }
      if (zone.contains(draggingEl)) return; // já está neste limbo

      const horarioId = parseInt(draggingEl.dataset.horarioId);
      fetch(base + '/horarios/geracao/mover', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          horario_id:       horarioId,
          novo_dia:         0,
          nova_hora_inicio: ''
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          if (data.anterior) guardarDesfazer({ tipo: 'mover', horario_id: horarioId, anterior: data.anterior });
          location.reload();
        } else {
          showToast(data.erro || 'Erro ao mover.', 'danger');
        }
      })
      .catch(() => showToast('Erro de comunicação.', 'danger'));
    });
  });

  // ── Desfazer último movimento (sobrevive ao reload) ───────────
  (function () {
    const raw = sessionStorage.getItem('sgaUndo');
    if (!raw) return;
    sessionStorage.removeItem('sgaUndo');

    let acao;
    try { acao = JSON.parse(raw); } catch { return; }
    if (!acao || Date.now() - (acao.ts || 0) > 60000) return; // só logo após o movimento

    const c = document.getElementById('toast-container');
    const d = document.createElement('div');
    d.className = 'alert alert-secondary py-2 px-3 mb-0 shadow d-flex align-items-center gap-2';
    d.style.fontSize = '13px';
    d.innerHTML = '<i class="bi bi-check-circle text-success"></i> Movimento aplicado. ' +
                  '<button type="button" class="btn btn-sm btn-outline-dark py-0">Desfazer</button>';
    c.appendChild(d);
    const timer = setTimeout(() => d.remove(), 10000);

    d.querySelector('button').addEventListener('click', () => {
      clearTimeout(timer);
      d.querySelector('button').disabled = true;

      const req = acao.tipo === 'trocar'
        ? { url: '/horarios/geracao/trocar', body: { horario_a: acao.a, horario_b: acao.b } }
        : { url: '/horarios/geracao/mover',  body: {
              horario_id:       acao.horario_id,
              novo_dia:         acao.anterior.dia,
              nova_hora_inicio: acao.anterior.dia === 0 ? '' : acao.anterior.hora_inicio
          } };

      fetch(req.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(req.body)
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          location.reload();
        } else {
          showToast(data.erro || 'Não foi possível desfazer.', 'danger');
          d.remove();
        }
      })
      .catch(() => { showToast('Erro de comunicação.', 'danger'); d.remove(); });
    });
  })();
})();
</script>
<?php endif; ?>

<script>
// Interruptores de exibição. Guardados em sessionStorage porque arrastar
// RECARREGA a página: sem isso a escolha se perdia a cada movimento. Como é
// sessão (e não localStorage), abrir o sistema de novo volta ao padrão marcado.
(function() {
  const liga = (id, classe, chave) => {
    const chk = document.getElementById(id);
    if (!chk) return;

    // Ausente = marcado. Só o '0' explícito desliga.
    chk.checked = sessionStorage.getItem(chave) !== '0';
    const aplicar = () => document.body.classList.toggle(classe, !chk.checked);
    aplicar();

    chk.addEventListener('change', () => {
      sessionStorage.setItem(chave, chk.checked ? '1' : '0');
      aplicar();
    });
  };
  liga('chkDispProf', 'sem-disp-prof', 'sgaDispProf');
  liga('chkLacunas',  'sem-lacunas',   'sgaLacunas');
})();

// Relatório da grade: mantém o painel aberto entre recargas (drag & drop recarrega a página)
(function () {
  const painel = document.getElementById('painelQualidade');
  if (!painel) return;
  if (localStorage.getItem('sgaRelatorioAberto') === '1') painel.classList.add('show');
  painel.addEventListener('shown.bs.collapse',  () => localStorage.setItem('sgaRelatorioAberto', '1'));
  painel.addEventListener('hidden.bs.collapse', () => localStorage.setItem('sgaRelatorioAberto', '0'));
})();

// Alterna entre a grade por turma e a grade por sala (páginas distintas).
(function () {
  const chk = document.getElementById('chkEnsalamento');
  if (!chk) return;
  chk.addEventListener('change', () => {
    // Leva os filtros junto: trocar de visão não deveria perder a seleção.
    if (chk.checked) window.location.href = base + '/horarios/geracao/<?= (int)$geracaoId ?>/grade/salas'
                                          + window.location.search;
  });
})();

// Liberação do horário para a consulta pública (/publico).
(function () {
  const chk = document.getElementById('chkPublico');
  if (!chk) return;

  chk.addEventListener('change', () => {
    fetch(base + '/horarios/geracao/publicar', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({geracao_id: <?= (int)$geracaoId ?>, publico: chk.checked ? 1 : 0})
    })
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { chk.checked = !chk.checked; showToast(data.erro || 'Falha ao alterar.', 'danger'); return; }
      showToast(data.publico
        ? 'Horário liberado para consulta pública.'
        : 'Horário retirado da consulta pública.', 'success');
    })
    .catch(() => { chk.checked = !chk.checked; showToast('Erro de comunicação.', 'danger'); });
  });
})();

// Ajuda da grade: popover no hover (e no foco, para quem navega por teclado).
// O bundle do Bootstrap é carregado no rodapé do layout, DEPOIS deste script:
// inicializar aqui direto encontraria `bootstrap` indefinido. Só o resto do JS
// da página escapa disso por usar bootstrap.* dentro de handlers.
document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('btnAjuda');
  const conteudo = document.getElementById('ajuda-conteudo');
  if (!btn || !conteudo || typeof bootstrap === 'undefined') return;

  new bootstrap.Popover(btn, {
    title: 'Como usar a grade',
    content: conteudo.innerHTML,
    html: true,
    sanitize: false,          // o conteúdo é nosso, e o sanitizador remove <span class>
    trigger: 'hover focus',
    placement: 'bottom',
    customClass: 'popover-ajuda'
  });
});

// Anotações da grade: duplo clique num bloco, ou o lápis no nome da turma.
// Salvar com o texto vazio remove — é a forma de apagar.
(function () {
  const modalEl = document.getElementById('modalAnotacao');
  if (!modalEl) return;

  const campo = document.getElementById('nota-texto');
  const alvo  = {tipo: null, id: null};

  function abrir(tipo, id, textoAtual, titulo) {
    alvo.tipo = tipo; alvo.id = id;
    document.getElementById('nota-titulo').textContent = titulo;
    campo.value = textoAtual || '';
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    setTimeout(() => campo.focus(), 250);
  }

  // Uma aula de N tempos vira N blocos com o MESMO data-horario-id: a anotação
  // precisa entrar (ou sair) de todos eles.
  function pintarBloco(horarioId, texto) {
    document.querySelectorAll('.disc-block[data-horario-id="' + horarioId + '"]').forEach(bloco => {
      // O span já existe dentro da linha do horário (vazio quando não há
      // anotação), então basta escrever nele — a cor vem por herança.
      let nota = bloco.querySelector('.disc-nota');
      if (!nota) {
        const hora = bloco.querySelector('.disc-hora');
        if (!hora) return;
        nota = document.createElement('span');
        nota.className = 'disc-nota';
        hora.appendChild(nota);
      }
      nota.textContent = texto;
    });
  }

  function gravar(texto) {
    fetch(base + '/horarios/geracao/anotar', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({tipo: alvo.tipo, id: alvo.id, texto: texto,
                            geracao_id: <?= (int)$geracaoId ?>})
    })
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { showToast(data.erro || 'Não foi possível salvar a anotação.', 'danger'); return; }

      if (alvo.tipo === 'turma') {
        const span = document.querySelector('.turma-nota[data-turma-id="' + alvo.id + '"]');
        if (span) span.textContent = data.texto;
      } else {
        pintarBloco(alvo.id, data.texto);
      }
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      showToast(data.texto ? 'Anotação salva.' : 'Anotação removida.', 'success');
    })
    .catch(() => showToast('Erro de comunicação.', 'danger'));
  }

  document.getElementById('nota-salvar').addEventListener('click', () => gravar(campo.value.trim()));
  document.getElementById('nota-remover').addEventListener('click', () => gravar(''));

  // Lápis no cabeçalho da turma
  document.querySelectorAll('.btn-nota[data-nota-tipo="turma"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id   = btn.dataset.notaId;
      const span = document.querySelector('.turma-nota[data-turma-id="' + id + '"]');
      abrir('turma', id, span ? span.textContent.trim() : '', 'Anotação da turma');
    });
  });

  // Duplo clique no bloco (não atrapalha o arraste, que usa mousedown+drag)
  document.querySelectorAll('.disc-block').forEach(bloco => {
    bloco.addEventListener('dblclick', () => {
      const nota = bloco.querySelector('.disc-nota');
      abrir('bloco', bloco.dataset.horarioId, nota ? nota.textContent.trim() : '',
            'Anotação da disciplina');
    });
  });
})();

// Exportação unificada: 3 escopos (turma, professor, sala) x 3 formatos
// (PDF, PNG, impressão). O escopo vem do item do menu; o formato, do botão
// clicado no rodapé do modal.
(function () {
  const modalEl = document.getElementById('modalImprimir');
  if (!modalEl) return;

  const todas = document.getElementById('imprimir-todas');
  const itens = () => Array.from(document.querySelectorAll('.imprimir-turma'));
  const botoes = () => Array.from(document.querySelectorAll('.exp-formato'));
  const orientacaoEl = document.getElementById('imprimir-orientacao');
  const BASE = '<?= $base ?>/horarios/geracao/<?= $geracaoId ?>';

  let escopo = 'turma';

  function sincronizarMestre() {
    if (!todas) return;
    const marcados = itens().filter(c => c.checked).length;
    todas.checked       = marcados === itens().length;
    todas.indeterminate = marcados > 0 && marcados < itens().length;
    // Sem turma marcada não há o que exportar — mas só no escopo turma.
    botoes().forEach(b => { b.disabled = (escopo === 'turma' && marcados === 0); });
  }

  if (todas) {
    todas.addEventListener('change', () => {
      itens().forEach(c => { c.checked = todas.checked; });
      todas.indeterminate = false;
      sincronizarMestre();
    });
    itens().forEach(c => c.addEventListener('change', sincronizarMestre));
  }

  // ── Escopo turma: tira do DOM as turmas não marcadas ─────────────
  // Esconder por CSS não serve: dependendo do navegador o bloco oculto ainda
  // conta para a quebra de página e sai uma folha em branco.
  let retirados = [];

  function retirarNaoSelecionados() {
    devolverRetirados();
    const ids = itens().filter(c => c.checked).map(c => c.value);
    document.querySelectorAll('.turma-bloco').forEach(bloco => {
      if (ids.includes(bloco.dataset.turmaId)) return;
      const marca = document.createComment('turma-oculta');
      bloco.parentNode.replaceChild(marca, bloco);
      retirados.push([marca, bloco]);
    });
  }

  function devolverRetirados() {
    retirados.forEach(([marca, bloco]) => {
      if (marca.parentNode) marca.parentNode.replaceChild(bloco, marca);
    });
    retirados = [];
  }

  // A orientação vive numa regra @page própria, que não pode ser selecionada
  // por classe — daí reescrever o conteúdo do <style>.
  const regra = document.getElementById('regra-pagina');
  const aplicarOrientacao = (valor) => {
    regra.textContent = '@page { size: ' + valor + '; margin: 8mm; }';
    document.documentElement.classList.toggle('print-retrato', valor === 'portrait');
  };

  // ── Abertura do modal: ajusta o que é exibido conforme o escopo ──
  modalEl.addEventListener('show.bs.modal', (ev) => {
    escopo = (ev.relatedTarget && ev.relatedTarget.dataset.escopo) || 'turma';

    const rotulos = {turma: 'Grade por turma', professor: 'Grade por professor', sala: 'Grade por sala'};
    const titulo = document.getElementById('exp-titulo');
    if (titulo) titulo.textContent = 'Exportar — ' + (rotulos[escopo] || rotulos.turma);

    const blocoTurmas = document.getElementById('exp-turmas');
    const avisoTudo   = document.getElementById('exp-aviso-tudo');
    const porTurma    = escopo === 'turma';
    if (blocoTurmas) blocoTurmas.style.display = porTurma ? '' : 'none';
    if (avisoTudo)   avisoTudo.style.display   = porTurma ? 'none' : '';

    sincronizarMestre();
  });

  // ── Ação: formato escolhido no rodapé ────────────────────────────
  botoes().forEach(btn => btn.addEventListener('click', () => {
    const formato = btn.dataset.formato;
    const orient  = orientacaoEl ? orientacaoEl.value : 'landscape';
    const ids     = itens().filter(c => c.checked).map(c => c.value).join(',');
    const fechar  = () => bootstrap.Modal.getOrCreateInstance(modalEl).hide();

    // PDF sai pronto do servidor (FPDF) nos três escopos.
    if (formato === 'pdf') {
      fechar();
      window.location.href = BASE + '/exportar/pdf?escopo=' + encodeURIComponent(escopo)
        + '&turmas=' + encodeURIComponent(ids) + '&orientacao=' + encodeURIComponent(orient);
      return;
    }

    // Professor e sala têm página própria (formato agenda); ela mesma imprime
    // ou gera o PNG conforme o parâmetro `acao`.
    if (escopo !== 'turma') {
      const alvo = escopo === 'professor' ? 'professores' : 'salas';
      fechar();
      window.open(BASE + '/imprimir/' + alvo + '?orientacao=' + encodeURIComponent(orient)
        + '&acao=' + encodeURIComponent(formato === 'png' ? 'png' : 'imprimir'), '_blank');
      return;
    }

    // Escopo turma, na própria página.
    if (formato === 'png') {
      fechar();
      modalEl.addEventListener('hidden.bs.modal', () => {
        retirarNaoSelecionados();
        html2canvas(document.getElementById('area-grade'), {backgroundColor: '#ffffff', scale: 2})
          .then(canvas => {
            const a = document.createElement('a');
            a.download = 'grade_por_turma_<?= date('Y-m-d') ?>.png';
            a.href = canvas.toDataURL('image/png');
            a.click();
          })
          .catch(err => showToast('Não foi possível gerar o PNG: ' + err, 'danger'))
          .finally(devolverRetirados);
      }, {once: true});
      return;
    }

    // Imprime só depois que o modal sumir de fato (evita o backdrop na página)
    modalEl.addEventListener('hidden.bs.modal', () => {
      aplicarOrientacao(orient);
      retirarNaoSelecionados();
      window.print();
    }, {once: true});
    fechar();
  }));

  // Só devolve depois que a impressão termina — devolver logo após print()
  // quebraria em navegador onde a chamada não bloqueia.
  let imprimindo = false;
  window.addEventListener('beforeprint', () => { imprimindo = true; });
  window.addEventListener('afterprint', () => { imprimindo = false; devolverRetirados(); });
  window.addEventListener('focus', () => { if (!imprimindo) devolverRetirados(); });
})();

// Verificação de conflitos entre disciplinas (independe de filtro)
document.getElementById('conflitos-verificar').addEventListener('click', () => {
  const out = document.getElementById('conflitos-resultado');
  out.innerHTML = '<div class="text-muted small">Verificando…</div>';

  fetch(base + '/horarios/geracao/<?= $geracaoId ?>/conflitos', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ disciplinas: document.getElementById('conflitos-input').value })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) {
      out.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(data.erro || 'Erro na verificação.') + '</div>';
      return;
    }

    let html = '';

    if (data.conflitos.length === 0) {
      html += '<div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>'
            + '<strong>Sem conflitos de horário</strong> — é possível cursar todas simultaneamente.</div>';
    } else {
      html += '<div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i>'
            + '<strong>' + data.conflitos.length + ' conflito(s) encontrado(s)</strong>'
            + ' — não é possível cursar todas ao mesmo tempo.</div>';
      data.conflitos.forEach(c => {
        html += '<div class="border border-danger-subtle rounded p-2 mb-1 small">'
              + '<strong>' + esc(c.dia) + '</strong>: '
              + esc(c.a) + ' <span class="badge bg-danger">' + esc(c.horario_a) + '</span>'
              + ' × '
              + esc(c.b) + ' <span class="badge bg-danger">' + esc(c.horario_b) + '</span>'
              + '</div>';
      });
    }

    if (data.nao_encontradas.length) {
      html += '<div class="alert alert-warning py-2 mt-2 mb-0 small"><i class="bi bi-question-circle me-1"></i>'
            + 'Não encontradas: <strong>' + data.nao_encontradas.map(esc).join('</strong>, <strong>') + '</strong></div>';
    }
    if (data.sem_horario.length) {
      html += '<div class="alert alert-info py-2 mt-2 mb-0 small"><i class="bi bi-inbox me-1"></i>'
            + 'Sem horário nesta geração (limbo): <strong>' + data.sem_horario.map(esc).join('</strong>, <strong>') + '</strong></div>';
    }

    out.innerHTML = html;
  })
  .catch(() => { out.innerHTML = '<div class="alert alert-danger py-2 mb-0">Erro de comunicação.</div>'; });

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }
});
</script>
