<?php $pageTitle = 'Professores'; ?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-success"></i>Professores</h5>
  <a href="/professores/novo" class="btn btn-success btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Novo Professor
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Nome</th>
            <th class="text-center">Status</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($professores)): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">Nenhum professor cadastrado.</td></tr>
        <?php else: ?>
        <?php foreach ($professores as $p): ?>
          <tr>
            <td class="text-muted small"><?= $p['id'] ?></td>
            <td class="fw-semibold">
              <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= htmlspecialchars($p['cor'] ?? '#3b82f6') ?>;margin-right:6px;vertical-align:middle;"></span>
              <?= htmlspecialchars($p['nome']) ?>
            </td>
            <td class="text-center">
              <span class="badge <?= $p['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>
              </span>
            </td>
            <td class="text-end">
              <a href="/professores/<?= $p['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="/professores/deletar" class="d-inline"
                    onsubmit="return confirm('Remover professor?')">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
