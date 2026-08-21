<?php $pageTitle = 'Grade por sala'; ?>

<?php // Mesmo estilo da grade por turma: as duas leem o mesmo parcial. ?>
<?php require VIEW_PATH . '/horarios/_grade_estilo.php'; ?>
<style>
<?php // Específico daqui: a régua vem dos horários reais, então as linhas não
      // têm altura fixa como na grade por turma. ?>
.grade-table tbody tr.faixa-row { height: auto; }
.grade-cell { cursor: default; }
@media print { .no-print { display: none !important; } .sala-wrap { break-inside: avoid; } }
</style>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap no-print">
  <a href="<?= $base ?>/horarios/geracao/<?= $geracaoId ?>/grade" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h5 class="mb-0 fw-semibold"><i class="bi bi-door-open me-2 text-primary"></i>Grade por sala</h5>
  <span class="badge bg-secondary"><?= htmlspecialchars($geracao['descricao'] ?? '') ?></span>

  <?php // Mesmo interruptor da grade por turma, aqui já marcado: desmarcar volta. ?>
  <div class="form-check form-switch mb-0 ms-2" title="Desmarque para voltar à grade por turma">
    <input class="form-check-input" type="checkbox" id="chkEnsalamento" checked>
    <label class="form-check-label small" for="chkEnsalamento">
      <i class="bi bi-door-open me-1"></i>Visualizar ensalamento
    </label>
  </div>
</div>

<script>
document.getElementById('chkEnsalamento')?.addEventListener('change', function () {
  if (!this.checked) {
    // Leva os filtros junto: trocar de visão não deveria perder a seleção.
    window.location.href = '<?= $base ?>/horarios/geracao/<?= (int)$geracaoId ?>/grade'
                         + window.location.search;
  }
});
</script>

<?php // Mesmos filtros da grade por turma, apontando para esta página. ?>
<form method="GET" action="<?= $base ?>/horarios/geracao/<?= $geracaoId ?>/grade/salas"
      class="row g-2 mb-3 align-items-end no-print">
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
    <a href="<?= $base ?>/horarios/geracao/<?= $geracaoId ?>/grade/salas" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-x-lg me-1"></i>Limpar filtros
    </a>
    <?php endif; ?>
  </div>
</form>

<?php if (empty($salas)): ?>
<div class="alert alert-info">
  <?= $filtroAtivo ? 'Nenhuma aula encontrada para esse filtro.' : 'Nenhuma aula agendada nesta geração.' ?>
</div>
<?php endif; ?>

<?php foreach ($salas as $sala): ?>
<div class="card border-0 shadow-sm mb-4 sala-wrap">
  <div class="card-body p-0 overflow-auto">
    <table class="grade-table w-100">
      <thead>
        <tr class="grade-turma-header">
          <td colspan="<?= count($dias) + 1 ?>">
            <i class="bi bi-<?= $sala['id'] === 0 ? 'exclamation-triangle' : 'door-open' ?> me-2"></i>
            <?= htmlspecialchars($sala['nome']) ?>
          </td>
        </tr>
        <tr>
          <th class="col-hora">Hora</th>
          <?php foreach ($dias as $dNome): ?><th><?= htmlspecialchars($dNome) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($sala['linhas'] as $idx => $linha): ?>
        <?php $altura = max(34, (int)round(($linha['fim'] - $linha['ini']) * 0.9)); ?>
        <tr class="faixa-row" style="height:<?= $altura ?>px">
          <td class="col-hora">
            <?= \App\Services\TimeHelper::fromMinutes($linha['ini']) ?>–<?= \App\Services\TimeHelper::fromMinutes($linha['fim']) ?>
          </td>
          <?php foreach ($dias as $dNum => $dNome): ?>
            <?php $h = $sala['grid'][$dNum][$idx] ?? null; ?>
            <?php if ($h === null): ?>
            <td class="grade-cell"></td>
            <?php else: ?>
            <?php
              $cor    = $h['professor_cor'] ?: '#94a3b8';
              $corSec = $h['professor_cor_secundaria'] ?: $cor;
              $txt    = \App\Services\ColorHelper::textoSobre($cor, 0.35);
              $txtSec = \App\Services\ColorHelper::textoSobre($corSec);
            ?>
            <td class="grade-cell" style="border-left:4px solid <?= htmlspecialchars($cor) ?>">
              <div class="disc-block" style="background:<?= htmlspecialchars($cor) ?>59">
                <div style="color:<?= $txt ?>;font-weight:700;font-size:12px">
                  <?= htmlspecialchars($h['disciplina_sigla'] ?: substr($h['disciplina_nome'], 0, 14)) ?>
                </div>
                <div class="disc-hora" style="color:<?= $txt ?>;font-weight:700;font-size:12px">
                  <?= htmlspecialchars($h['curso_nome'] . ' – ' . $h['turma_nome']) ?><span
                    class="disc-nota"><?= !empty($h['observacao']) ? htmlspecialchars($h['observacao']) : '' ?></span>
                </div>
                <?php if ($h['professor_nome']): ?>
                <div class="disc-faixa" style="background:<?= htmlspecialchars($corSec) ?>;color:<?= $txtSec ?>">
                  <?= htmlspecialchars(substr($h['professor_nome'], 0, 26)) ?>
                </div>
                <?php else: ?>
                <div style="position:absolute;bottom:0;left:0;right:0;height:5px;background:<?= htmlspecialchars($corSec) ?>"></div>
                <?php endif; ?>
              </div>
            </td>
            <?php endif; ?>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
