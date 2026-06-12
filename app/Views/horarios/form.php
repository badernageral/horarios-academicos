<?php
$pageTitle = $semestre ? 'Editar Semestre' : 'Novo Semestre';
$anoAtual  = (int)date('Y');
?>

<div class="d-flex align-items-center mb-3">
  <a href="/horarios" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i><?= $pageTitle ?></h5>
</div>

<div class="card border-0 shadow-sm" style="max-width:440px">
  <div class="card-body">
    <form method="POST" action="/horarios/salvar">
      <?php if ($semestre): ?>
      <input type="hidden" name="id" value="<?= $semestre['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-7">
          <label class="form-label">Semestre <span class="text-danger">*</span></label>
          <select name="semestre" class="form-select" required autofocus>
            <option value="1" <?= ($semestre['semestre'] ?? 1) == 1 ? 'selected' : '' ?>>1º Semestre</option>
            <option value="2" <?= ($semestre['semestre'] ?? 1) == 2 ? 'selected' : '' ?>>2º Semestre</option>
          </select>
        </div>
        <div class="col-5">
          <label class="form-label">Ano <span class="text-danger">*</span></label>
          <input type="number" name="ano" class="form-control"
                 value="<?= (int)($semestre['ano'] ?? $anoAtual) ?>"
                 min="2020" max="2040" required>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i><?= $semestre ? 'Salvar' : 'Criar Semestre' ?>
        </button>
        <a href="/horarios" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>
