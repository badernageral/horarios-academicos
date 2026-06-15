<?php
$pageTitle = 'Turmas';
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
  <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2 text-info"></i>Turmas</h5>
  <div class="d-flex gap-2">
    <a href="<?= $base ?>/turmas/importar" class="btn btn-outline-info btn-sm">
      <i class="bi bi-cloud-upload me-1"></i>Importar em Massa
    </a>
    <a href="<?= $base ?>/turmas/nova" class="btn btn-info btn-sm text-white">
      <i class="bi bi-plus-lg me-1"></i>Nova Turma
    </a>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <?= $th('curso_nome', 'Curso') ?>
            <?= $th('serie_periodo', 'Série/Período') ?>
            <?= $th('ativo', 'Status', ' class="text-center"') ?>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($turmas)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma turma cadastrada.</td></tr>
        <?php else: ?>
        <?php foreach ($turmas as $t): ?>
          <tr>
            <td class="text-muted small"><?= $t['id'] ?></td>
            <td>
              <span class="color-dot me-1" style="background:<?= htmlspecialchars($t['curso_cor'] ?? '#aaa') ?>;
                width:10px;height:10px;border-radius:50%;display:inline-block"></span>
              <?= htmlspecialchars($t['curso_nome']) ?>
            </td>
            <td><?= htmlspecialchars($t['serie_periodo']) ?></td>
            <td class="text-center">
              <span class="badge <?= $t['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $t['ativo'] ? 'Ativa' : 'Inativa' ?>
              </span>
            </td>
            <td class="text-end">
              <a href="<?= $base ?>/turmas/<?= $t['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="<?= $base ?>/turmas/deletar" class="d-inline"
                    onsubmit="return confirm('Remover turma?')">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
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
