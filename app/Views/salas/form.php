<?php $pageTitle = $sala ? 'Editar Sala' : 'Nova Sala'; ?>

<div class="d-flex align-items-center mb-3">
  <a href="<?= $base ?>/salas" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><?= $pageTitle ?></h5>
</div>

<div class="card border-0 shadow-sm" style="max-width:400px">
  <div class="card-body">
    <form method="POST" action="<?= $base ?>/salas/salvar">
      <?php if ($sala): ?>
      <input type="hidden" name="id" value="<?= $sala['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Nome da Sala <span class="text-danger">*</span></label>
          <input type="text" name="nome" class="form-control"
                 value="<?= htmlspecialchars($sala['nome'] ?? '') ?>"
                 placeholder="Ex: Sala 101" required>
        </div>
        <div class="col-12">
          <label class="form-label">Status</label>
          <select name="ativo" class="form-select">
            <option value="1" <?= ($sala['ativo'] ?? 1) == 1 ? 'selected':'' ?>>Ativa</option>
            <option value="0" <?= ($sala['ativo'] ?? 1) == 0 ? 'selected':'' ?>>Inativa</option>
          </select>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Salvar
          </button>
          <a href="<?= $base ?>/salas" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </div>
    </form>
  </div>
</div>
