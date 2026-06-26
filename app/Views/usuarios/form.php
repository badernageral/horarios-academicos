<?php $pageTitle = $usuario ? 'Editar Usuário' : 'Novo Usuário'; ?>

<div class="d-flex align-items-center mb-3">
  <a href="<?= $base ?>/usuarios" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><?= $pageTitle ?></h5>
</div>

<div class="card border-0 shadow-sm" style="max-width:480px">
  <div class="card-body">
    <form method="POST" action="<?= $base ?>/usuarios/salvar">
      <?php if ($usuario): ?>
      <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Nome <span class="text-danger">*</span></label>
          <input type="text" name="nome" class="form-control"
                 value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>"
                 placeholder="Ex: José Robson" required>
        </div>
        <div class="col-12">
          <label class="form-label">Usuário (login) <span class="text-danger">*</span></label>
          <input type="text" name="usuario" class="form-control"
                 value="<?= htmlspecialchars($usuario['usuario'] ?? '') ?>"
                 placeholder="ex: jrobson" autocomplete="off" required>
        </div>
        <div class="col-12">
          <label class="form-label">
            Senha <?= $usuario ? '' : '<span class="text-danger">*</span>' ?>
          </label>
          <input type="password" name="senha" class="form-control" autocomplete="new-password"
                 placeholder="<?= $usuario ? 'Deixe em branco para manter a atual' : 'Defina a senha' ?>"
                 <?= $usuario ? '' : 'required' ?>>
          <?php if ($usuario): ?>
          <div class="form-text">Preencha apenas se quiser trocar a senha.</div>
          <?php endif; ?>
        </div>
        <div class="col-12">
          <label class="form-label">Status</label>
          <select name="ativo" class="form-select">
            <option value="1" <?= ($usuario['ativo'] ?? 1) == 1 ? 'selected' : '' ?>>Ativo</option>
            <option value="0" <?= ($usuario['ativo'] ?? 1) == 0 ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Salvar
          </button>
          <a href="<?= $base ?>/usuarios" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </div>
    </form>
  </div>
</div>
