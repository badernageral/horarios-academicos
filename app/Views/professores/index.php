<?php
$pageTitle = 'Professores';
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
  <h5 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-success"></i>Professores</h5>
  <div class="d-flex gap-2">
    <?php if ($temCoresRepetidas ?? false): ?>
    <form method="POST" action="/professores/corrigir-cores" class="d-inline">
      <button type="submit" class="btn btn-warning btn-sm">
        <i class="bi bi-palette me-1"></i>Corrigir Cores Repetidas
      </button>
    </form>
    <?php endif; ?>
    <a href="/professores/importar" class="btn btn-outline-success btn-sm">
      <i class="bi bi-cloud-upload me-1"></i>Importar em Massa
    </a>
    <a href="/professores/novo" class="btn btn-success btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Novo Professor
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
            <?= $th('nome', 'Nome') ?>
            <?= $th('nda_nome', 'NDA') ?>
            <?= $th('ativo', 'Status', ' class="text-center"') ?>
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
              <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:linear-gradient(to right,<?= htmlspecialchars($p['cor'] ?? '#3b82f6') ?> 50%,<?= htmlspecialchars($p['cor_secundaria'] ?? '#f97316') ?> 50%);margin-right:6px;vertical-align:middle;border:1px solid rgba(0,0,0,.12)"></span>
              <?= htmlspecialchars($p['nome']) ?>
            </td>
            <td class="text-muted small"><?= htmlspecialchars($p['nda_nome'] ?? '—') ?></td>
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
