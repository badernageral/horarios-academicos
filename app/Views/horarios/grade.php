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
  padding: 4px 7px;
  border-radius: 5px;
  line-height: 1.35;
  box-sizing: border-box;
  transition: box-shadow 0.1s;
}
.disc-block:hover { box-shadow: 0 2px 6px rgba(0,0,0,.15); }
.disc-block.dragging { opacity: 0.3; cursor: grabbing; }
.disc-block * { pointer-events: none; }
#toast-container {
  position: fixed; bottom: 1.5rem; right: 1.5rem;
  z-index: 9999; display: flex; flex-direction: column; gap: 8px;
}
</style>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/horarios" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-semibold"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Grade de Horários</h5>
  <span class="badge bg-secondary ms-auto">Geração #<?= $geracaoId ?> – <?= htmlspecialchars($geracao['descricao'] ?? '') ?></span>
</div>

<?php if (empty($grade)): ?>
<div class="alert alert-info">Nenhum horário encontrado para esta geração.</div>
<?php else: ?>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0 overflow-auto">
    <table class="grade-table w-100">
      <thead>
        <tr>
          <th class="col-hora">Hora</th>
          <?php foreach ($dias as $dNome): ?>
          <th><?= $dNome ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($grade as $turmaId => $row): ?>

        <!-- Cabeçalho da turma -->
        <tr class="grade-turma-header">
          <td colspan="6">
            <?= htmlspecialchars($row['curso_nome']) ?> — <?= htmlspecialchars($row['turma_nome']) ?>
          </td>
        </tr>

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
              $h    = $row['grid'][$dNum][$slotIdx];
              $cor  = !empty($h['professor_cor']) ? $h['professor_cor'] : $corSemProf;
              $bg   = $cor . '18';
              $span = $h['rowspan'];
            ?>
            <td class="grade-cell p-1"
                data-dia="<?= $dNum ?>"
                data-turma-id="<?= $turmaId ?>"
                data-slot="<?= $slotIdx ?>"
                data-hora-inicio="<?= substr($h['hora_inicio'],0,5) ?>"
                rowspan="<?= $span ?>"
                style="background:<?= $bg ?>;border-left:4px solid <?= $cor ?>">
              <div class="disc-block"
                   draggable="true"
                   data-horario-id="<?= $h['id'] ?>"
                   data-dia="<?= $dNum ?>"
                   data-slot="<?= $slotIdx ?>"
                   data-rowspan="<?= $span ?>"
                   data-turma-id="<?= $turmaId ?>">
                <div style="color:<?= $cor ?>;font-weight:700;font-size:12px">
                  <?= htmlspecialchars($h['disciplina_sigla'] ?: mb_substr($h['disciplina_nome'], 0, 14)) ?>
                </div>
                <div style="color:<?= $cor ?>;font-weight:700;font-size:12px">
                  <?= \App\Services\TimeHelper::fromMinutes($h['slot_ini']) ?>–<?= \App\Services\TimeHelper::fromMinutes($h['slot_fim']) ?>
                </div>
                <?php if ($h['professor_nome']): ?>
                <div style="color:<?= $cor ?>;font-weight:700;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px">
                  <?= htmlspecialchars(mb_substr($h['professor_nome'], 0, 22)) ?>
                </div>
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

      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<div id="toast-container"></div>

<script>
(function () {
  let draggingEl    = null;
  let draggingTurma = null;

  function showToast(msg, type) {
    const c = document.getElementById('toast-container');
    const d = document.createElement('div');
    d.className = 'alert alert-' + type + ' py-2 px-3 mb-0 shadow-sm';
    d.style.fontSize = '13px';
    d.textContent = msg;
    c.appendChild(d);
    setTimeout(() => d.remove(), 3500);
  }

  function isValidTarget(cell) {
    return cell.dataset.turmaId === draggingTurma
        && !cell.querySelector('.disc-block');
  }

  document.querySelectorAll('.disc-block').forEach(el => {
    el.addEventListener('dragstart', e => {
      draggingEl    = el;
      draggingTurma = el.dataset.turmaId;
      el.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    el.addEventListener('dragend', () => {
      el.classList.remove('dragging');
      draggingEl = draggingTurma = null;
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
      if (cell.querySelector('.disc-block')) {
        showToast('Já existe uma disciplina neste horário.', 'warning');
        return;
      }

      const el             = draggingEl;
      const novoDia        = parseInt(cell.dataset.dia);
      const novaHoraInicio = cell.dataset.horaInicio;

      fetch('/horarios/geracao/mover', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          horario_id:       parseInt(el.dataset.horarioId),
          novo_dia:         novoDia,
          nova_hora_inicio: novaHoraInicio
        })
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          location.reload();
        } else {
          showToast(data.erro || 'Erro ao mover.', 'danger');
        }
      })
      .catch(() => showToast('Erro de comunicação.', 'danger'));
    });
  });
})();
</script>
