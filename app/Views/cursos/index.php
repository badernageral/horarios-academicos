<?php
$pageTitle = 'Cursos';
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
  <h5 class="mb-0 fw-semibold"><i class="bi bi-mortarboard me-2 text-primary"></i>Cursos</h5>
  <a href="<?= $base ?>/cursos/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Novo Curso
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Cor</th>
            <?= $th('nome', 'Nome') ?>
            <th>Turno</th>
            <th>Dias</th>
            <th>Intervalos</th>
            <?= $th('ativo', 'Status', ' class="text-center"') ?>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $diasAbrev = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb'];
        if (empty($cursos)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Nenhum curso cadastrado.</td></tr>
        <?php else: ?>
        <?php foreach ($cursos as $c):
          $dias = json_decode($c['dias_semana'], true);
          $diasStr = implode(', ', array_map(fn($d) => $diasAbrev[(int)$d] ?? $d, $dias ?? []));
          // Count intervalos
          $countInterv = \App\Core\Database::fetchValue(
            "SELECT COUNT(*) FROM intervalos_curso WHERE curso_id=?", [$c['id']]
          );
          ?>
          <tr>
            <td class="text-muted small"><?= $c['id'] ?></td>
            <td>
              <span class="color-dot" style="background:<?= htmlspecialchars($c['cor']) ?>;
                width:20px;height:20px;border-radius:50%;display:inline-block;border:1px solid #ccc"></span>
            </td>
            <td class="fw-semibold"><?= htmlspecialchars($c['nome']) ?></td>
            <td>
              <code><?= substr($c['turno_inicio'],0,5) ?> – <?= substr($c['turno_fim'],0,5) ?></code>
            </td>
            <td><small><?= $diasStr ?></small></td>
            <td><span class="badge bg-secondary"><?= $countInterv ?></span></td>
            <td class="text-center">
              <span class="badge <?= $c['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
              </span>
            </td>
            <td class="text-end">
              <a href="<?= $base ?>/cursos/<?= $c['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="<?= $base ?>/cursos/deletar" class="d-inline"
                    onsubmit="return confirm('Remover curso?')">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
