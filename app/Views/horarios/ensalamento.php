<?php
$pageTitle    = 'Atribuição de Salas';
$semestreLabel = $semestre['semestre'] . 'º Semestre / ' . $semestre['ano'];
$semSala      = count(array_filter($turmas, fn($t) => !$t['sala_atribuida']));
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $base ?>/horarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h5 class="mb-0 fw-semibold"><i class="bi bi-door-open me-2 text-secondary"></i>Atribuição de Salas</h5>
    <small class="text-muted"><?= $semestreLabel ?></small>
  </div>
  <?php if ($semSala > 0): ?>
  <span class="badge bg-warning text-dark ms-auto"><?= $semSala ?> sem sala</span>
  <?php else: ?>
  <span class="badge bg-success ms-auto"><i class="bi bi-check-lg me-1"></i>Todas atribuídas</span>
  <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <form method="POST" action="<?= $base ?>/horarios/<?= $semestreId ?>/ensalamento">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Turma</th>
            <th>Curso</th>
            <th style="min-width:220px">Sala</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($turmas as $t): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($t['serie_periodo']) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($t['curso_nome']) ?></td>
            <td>
              <select name="ensalamento[<?= $t['id'] ?>]" class="form-select form-select-sm">
                <option value="">— Sem sala —</option>
                <?php foreach ($salas as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $t['sala_atribuida'] == $s['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($s['nome']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
      <button type="submit" class="btn btn-secondary">
        <i class="bi bi-check-lg me-1"></i>Salvar Salas
      </button>
      <a href="<?= $base ?>/horarios" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
</div>
