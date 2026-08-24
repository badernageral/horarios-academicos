<?php
$pageTitle = 'Disciplinas';
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
  <h5 class="mb-0 fw-semibold"><i class="bi bi-book me-2 text-warning"></i>Disciplinas</h5>
  <div class="d-flex gap-2">
    <a href="<?= $base ?>/disciplinas/importar" class="btn btn-outline-warning btn-sm">
      <i class="bi bi-cloud-upload me-1"></i>Importar em Massa
    </a>
    <a href="<?= $base ?>/disciplinas/nova" class="btn btn-warning btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Nova Disciplina
    </a>
  </div>
</div>

<?php if (empty($grupos)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center text-muted py-4">Nenhuma disciplina cadastrada.</div>
</div>
<?php endif; ?>

<?php // Um card por CURSO; dentro dele, uma faixa por TURMA separando as disciplinas.
      // Curso e turma saem das colunas: já estão no cabeçalho e na faixa. ?>
<?php foreach ($grupos as $g): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
    <div class="fw-semibold">
      <i class="bi bi-mortarboard me-2 text-warning"></i><?= htmlspecialchars($g['nome']) ?>
      <span class="badge text-bg-light border ms-1"><?= count($g['turmas']) ?> turma(s)</span>
      <span class="badge text-bg-light border"><?= $g['qtd'] ?> disciplina(s)</span>
    </div>
    <span class="small text-muted">
      Total semanal: <strong><?= \App\Services\TimeHelper::formatDuration($g['minutos']) ?></strong>
    </span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <?= $th('sigla', 'Sigla') ?>
            <?= $th('nome', 'Nome') ?>
            <?= $th('nda_nome', 'NDA') ?>
            <th class="text-center">Semestre</th>
            <?= $th('qtd_encontros_semanais', 'Encontros/sem', ' class="text-center"') ?>
            <th class="text-center">Aulas/encontro</th>
            <th class="text-center">Duração/encontro</th>
            <th class="text-center">Total/sem</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($g['turmas'] as $t): ?>
          <tr class="table-light">
            <td colspan="10" class="py-1 small">
              <i class="bi bi-people me-1 text-secondary"></i>
              <span class="fw-semibold"><?= htmlspecialchars($t['nome']) ?></span>
              <span class="text-muted">
                · <?= count($t['disciplinas']) ?> disciplina(s)
                · <?= \App\Services\TimeHelper::formatDuration($t['minutos']) ?>/semana
              </span>
            </td>
          </tr>
          <?php foreach ($t['disciplinas'] as $d):
            $duracaoEncontro = (int)$d['qtd_aulas'] * (int)$d['duracao_aula_minutos'];
            $totalMin        = (int)$d['qtd_encontros_semanais'] * $duracaoEncontro;
            ?>
            <tr>
              <td class="text-muted small"><?= $d['id'] ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($d['sigla']) ?></td>
              <td><?= htmlspecialchars($d['nome']) ?></td>
              <td class="small">
                <?php if (!empty($d['nda_nome'])): ?>
                  <?= htmlspecialchars($d['nda_nome']) ?>
                <?php else: ?>
                  <span class="text-muted fst-italic">Qualquer NDA</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php
                  $o = (int)$d['semestre_oferta'];
                  if ($o === 3)      echo '<span class="badge bg-secondary">Anual</span>';
                  elseif ($o === 1)  echo '<span class="badge bg-primary">1º Sem</span>';
                  elseif ($o === 2)  echo '<span class="badge bg-info text-dark">2º Sem</span>';
                ?>
              </td>
              <td class="text-center">
                <span class="badge bg-primary"><?= $d['qtd_encontros_semanais'] ?>×</span>
              </td>
              <td class="text-center">
                <span class="badge bg-info text-dark"><?= $d['qtd_aulas'] ?> aula<?= $d['qtd_aulas'] > 1 ? 's' : '' ?></span>
              </td>
              <td class="text-center">
                <span class="badge bg-secondary"><?= \App\Services\TimeHelper::formatDuration($duracaoEncontro) ?></span>
              </td>
              <td class="text-center">
                <span class="badge bg-dark"><?= \App\Services\TimeHelper::formatDuration($totalMin) ?></span>
              </td>
              <td class="text-end">
                <a href="<?= $base ?>/disciplinas/<?= $d['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" action="<?= $base ?>/disciplinas/deletar" class="d-inline"
                      onsubmit="return confirm('Remover disciplina?')">
                  <input type="hidden" name="id" value="<?= $d['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>
