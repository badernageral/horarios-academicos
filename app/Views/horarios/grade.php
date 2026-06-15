<?php
$pageTitle = 'Grade de Horários';
$dias = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta'];

$corSemProf = '#94a3b8';
?>

<style>
.grade-table {
  border-collapse: collapse;
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
  min-width: 80px;
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
.grade-cell {
  vertical-align: top;
  padding: 3px;
  border: 1px solid #e2e8f0;
  min-width: 130px;
  background: #fff;
  transition: background 0.12s;
}
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
.disc-block {
  cursor: grab;
  user-select: none;
  padding: 4px 7px 24px;
  border-radius: 5px;
  line-height: 1.35;
  box-sizing: border-box;
  transition: box-shadow 0.1s;
  position: relative;
  overflow: hidden;
}
.disc-block:hover { box-shadow: 0 2px 6px rgba(0,0,0,.15); }
.disc-block.dragging { opacity: 0.3; cursor: grabbing; }
.disc-block * { pointer-events: none; }
.disc-faixa {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  padding: 3px 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
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
  /* Uma turma por página */
  .turma-bloco + .turma-bloco { break-before: page; page-break-before: always; }
  .turma-bloco { break-inside: avoid-page; }
  .content-wrapper { padding: 0 !important; }
  #page-content { width: 100% !important; }
  .card { box-shadow: none !important; border: none !important; }
  .card-body { overflow: visible !important; }
  body { background: #fff !important; }
  * { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .grade-table th, .grade-table th.col-hora, .grade-table td.col-hora,
  .grade-turma-header td { position: static !important; }
  .disc-block { cursor: default; }
}
@page { size: landscape; margin: 8mm; }
</style>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap no-print">
  <a href="<?= $base ?>/horarios" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-semibold"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Grade de Horários</h5>
  <span class="badge bg-secondary ms-auto"><?= htmlspecialchars($geracao['descricao'] ?? '') ?></span>
  <div class="dropdown">
    <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown">
      <i class="bi bi-printer me-1"></i>Imprimir
    </button>
    <ul class="dropdown-menu">
      <li>
        <a class="dropdown-item" href="#" onclick="window.print(); return false;">
          <i class="bi bi-grid-3x3-gap me-2"></i>Grade (por turma)
        </a>
      </li>
      <li>
        <a class="dropdown-item" href="<?= $base ?>/horarios/geracao/<?= $geracaoId ?>/imprimir/professores" target="_blank">
          <i class="bi bi-person-badge me-2"></i>Todos por professor
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
<div class="mb-3 no-print">
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
              <th class="text-center">Carga semanal</th>
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
  <div class="card-body p-0 overflow-auto">
      <?php foreach ($grade as $turmaId => $row): ?>

    <!-- Uma tabela por turma (na impressão, uma turma por página) -->
    <div class="turma-bloco">
    <table class="grade-table w-100">
      <thead>
        <!-- Cabeçalho da turma -->
        <tr class="grade-turma-header">
          <td colspan="6">
            <?= htmlspecialchars($row['curso_nome']) ?> — <?= htmlspecialchars($row['turma_nome']) ?>
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
        <?php foreach ($row['slots'] as $slotIdx => $slot): ?>
        <?php if ($slot['type'] === 'intervalo'): ?>

        <!-- Linha de intervalo -->
        <tr>
          <td class="col-hora" style="background:#fef9c3;color:#92400e;font-style:italic;font-size:10px;">
            <?= \App\Services\TimeHelper::fromMinutes($slot['min']) ?>–<?= \App\Services\TimeHelper::fromMinutes($slot['fim']) ?>
          </td>
          <td colspan="5" style="background:#fef9c3;border:1px solid #fde68a;text-align:center;font-size:10px;color:#92400e;font-style:italic;padding:2px;">
            <i class="bi bi-cup-hot" style="font-size:10px;"></i> Intervalo
          </td>
        </tr>

        <?php else: /* slot de aula */ ?>
        <?php $slotMin = $slot['min']; $slotFimMin = $slot['fim']; ?>
        <tr data-turma-id="<?= $turmaId ?>">

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
              $span   = $h['rowspan'];
            ?>
            <td class="grade-cell p-1"
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
                   data-turma-id="<?= $turmaId ?>">
                <div style="color:#000;font-weight:700;font-size:12px">
                  <?= htmlspecialchars($h['disciplina_sigla'] ?: substr($h['disciplina_nome'], 0, 14)) ?>
                </div>
                <div style="color:#000;font-weight:700;font-size:12px">
                  <?= \App\Services\TimeHelper::fromMinutes($h['slot_ini']) ?>–<?= \App\Services\TimeHelper::fromMinutes($h['slot_fim']) ?>
                </div>
                <?php if ($h['professor_nome']): ?>
                <div class="disc-faixa" style="background:<?= $corSec ?>">
                  <?= htmlspecialchars(substr($h['professor_nome'], 0, 26)) ?>
                </div>
                <?php else: ?>
                <div style="position:absolute;bottom:0;left:0;right:0;height:5px;background:<?= $corSec ?>"></div>
                <?php endif; ?>
              </div>
            </td>
          <?php else: ?>
            <td class="grade-cell"
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
              ?>
              <div class="disc-block"
                   draggable="<?= $filtroAtivo ? 'false' : 'true' ?>"
                   data-horario-id="<?= $h['id'] ?>"
                   data-turma-id="<?= $turmaId ?>"
                   style="background:<?= $cor ?>59">
                <div style="color:#000;font-weight:700;font-size:12px">
                  <?= htmlspecialchars($h['disciplina_sigla'] ?: substr($h['disciplina_nome'], 0, 14)) ?>
                </div>
                <?php if ($h['professor_nome']): ?>
                <div class="disc-faixa" style="background:<?= $corSec ?>">
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

<?php if (!$filtroAtivo): /* drag & drop desabilitado quando há filtro */ ?>
<script>
const base = '<?= $base ?>';
(function () {
  let draggingEl    = null;
  let draggingTurma = null;
  let draggingLimbo = false;

  function showToast(msg, type) {
    const c = document.getElementById('toast-container');
    const d = document.createElement('div');
    d.className = 'alert alert-' + type + ' py-2 px-3 mb-0 shadow-sm';
    d.style.fontSize = '13px';
    d.textContent = msg;
    c.appendChild(d);
    setTimeout(() => d.remove(), 3500);
  }

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

  document.querySelectorAll('.disc-block').forEach(el => {
    el.addEventListener('dragstart', e => {
      draggingEl    = el;
      draggingTurma = el.dataset.turmaId;
      draggingLimbo = !!el.closest('.limbo-zone');
      el.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    el.addEventListener('dragend', () => {
      el.classList.remove('dragging');
      draggingEl = draggingTurma = null;
      draggingLimbo = false;
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
// Relatório da grade: mantém o painel aberto entre recargas (drag & drop recarrega a página)
(function () {
  const painel = document.getElementById('painelQualidade');
  if (!painel) return;
  if (localStorage.getItem('sgaRelatorioAberto') === '1') painel.classList.add('show');
  painel.addEventListener('shown.bs.collapse',  () => localStorage.setItem('sgaRelatorioAberto', '1'));
  painel.addEventListener('hidden.bs.collapse', () => localStorage.setItem('sgaRelatorioAberto', '0'));
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
