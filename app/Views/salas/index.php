<?php
$pageTitle = 'Salas';
$th = function(string $col, string $label, string $extra = '') use ($sort, $dir) {
    $nd   = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $sort === $col
        ? ($dir === 'asc' ? '<i class="bi bi-sort-up ms-1"></i>' : '<i class="bi bi-sort-down ms-1"></i>')
        : '<i class="bi bi-arrow-down-up ms-1 text-muted opacity-50" style="font-size:.75em"></i>';
    return "<th{$extra}><a href=\"?sort={$col}&dir={$nd}\" class=\"text-decoration-none text-dark\">{$label}{$icon}</a></th>";
};
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0 fw-semibold"><i class="bi bi-door-open me-2 text-secondary"></i>Salas</h5>
  <a href="/salas/nova" class="btn btn-secondary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Nova Sala
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <?= $th('nome', 'Nome') ?>
            <?= $th('ativo', 'Status', ' class="text-center"') ?>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($salas)): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma sala cadastrada.</td></tr>
        <?php else: ?>
        <?php foreach ($salas as $s): ?>
          <tr>
            <td class="text-muted small"><?= $s['id'] ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($s['nome']) ?></td>
            <td class="text-center">
              <span class="badge <?= $s['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $s['ativo'] ? 'Ativa' : 'Inativa' ?>
              </span>
            </td>
            <td class="text-end">
              <a href="/salas/<?= $s['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="/salas/deletar" class="d-inline"
                    onsubmit="return confirm('Remover sala?')">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
