<?php $pageTitle = $turma ? 'Editar Turma' : 'Nova Turma'; ?>

<div class="d-flex align-items-center mb-3">
  <a href="/turmas" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><?= $pageTitle ?></h5>
</div>

<div class="card border-0 shadow-sm" style="max-width:500px">
  <div class="card-body">
    <form method="POST" action="/turmas/salvar">
      <?php if ($turma): ?>
      <input type="hidden" name="id" value="<?= $turma['id'] ?>">
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Curso <span class="text-danger">*</span></label>
        <select name="curso_id" class="form-select" required>
          <option value="">Selecione...</option>
          <?php foreach ($cursos as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($turma['curso_id'] ?? '') == $c['id'] ? 'selected':'' ?>>
            <?= htmlspecialchars($c['nome']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Série/Período <span class="text-danger">*</span></label>
          <input type="text" name="serie_periodo" class="form-control"
                 value="<?= htmlspecialchars($turma['serie_periodo'] ?? '') ?>"
                 placeholder="Ex: 1º Ano" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="ativo" class="form-select">
            <option value="1" <?= ($turma['ativo'] ?? 1) == 1 ? 'selected':'' ?>>Ativa</option>
            <option value="0" <?= ($turma['ativo'] ?? 1) == 0 ? 'selected':'' ?>>Inativa</option>
          </select>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i>Salvar
        </button>
        <a href="/turmas" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>
