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
    <a href="<?= $base ?>/professores/importar" class="btn btn-outline-success btn-sm">
      <i class="bi bi-cloud-upload me-1"></i>Importar em Massa
    </a>
    <a href="<?= $base ?>/professores/novo" class="btn btn-success btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Novo Professor
    </a>
  </div>
</div>

<?php if (empty($grupos)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center text-muted py-4">Nenhum professor cadastrado.</div>
</div>
<?php endif; ?>

<?php foreach ($grupos as $g): ?>
<?php $conflitantes = $g['conflitantes']; $qtdConflitos = count($conflitantes); ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
    <div class="fw-semibold">
      <i class="bi bi-diagram-3 me-2 text-secondary"></i><?= htmlspecialchars($g['nome']) ?>
      <span class="badge text-bg-light border ms-1"><?= count($g['professores']) ?></span>
      <?php if ($qtdConflitos): ?>
      <span class="badge text-bg-danger ms-1" title="professores com cores repetidas neste NDA">
        <?= $qtdConflitos ?> com cores repetidas
      </span>
      <?php endif; ?>
    </div>
    <form method="POST" action="<?= $base ?>/professores/corrigir-cores" class="d-inline"
          onsubmit="return confirm('Reatribuir cores dos professores duplicados em <?= htmlspecialchars(addslashes($g['nome'])) ?>? A primeira ocorrência de cada par de cores é mantida.');">
      <input type="hidden" name="nda_id" value="<?= (int)$g['id'] ?>">
      <button type="submit" class="btn btn-sm <?= $qtdConflitos ? 'btn-warning' : 'btn-outline-secondary' ?>"
              title="Dá cores novas apenas aos professores com cores repetidas neste NDA">
        <i class="bi bi-palette me-1"></i>Corrigir cores
      </button>
    </form>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <?= $th('nome', 'Nome') ?>
            <?php // o usuário do Moodle é a matrícula do professor ?>
            <?= $th('usuario_moodle', 'Matrícula') ?>
            <?= $th('ativo', 'Status', ' class="text-center"') ?>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($g['professores'] as $p):
          $conflito = $conflitantes[$p['id']] ?? null;
        ?>
          <tr <?= $conflito ? 'class="table-warning"' : '' ?>>
            <td class="text-muted small"><?= $p['id'] ?></td>
            <?php
              // Mesma linguagem visual dos blocos da grade: fundo na cor
              // primária a ~35% (sufixo alpha 59) e o nome do professor dentro
              // de um retângulo na cor secundária cheia. A cor do texto sai do
              // ColorHelper, medindo cada superfície com a sua opacidade.
              $corPri = $p['cor'] ?? '#3b82f6';
              $corSec = $p['cor_secundaria'] ?? '#f97316';
            ?>
            <td style="background-color:<?= htmlspecialchars($corPri) ?>59">
              <span class="prof-faixa"
                    style="background:<?= htmlspecialchars($corSec) ?>;
                           color:<?= \App\Services\ColorHelper::textoSobre($corSec) ?>">
                <?= htmlspecialchars($p['nome']) ?>
              </span>
              <?php if ($conflito): ?>
              <?php // chip claro: o aviso precisa continuar legível sobre qualquer fundo ?>
              <div class="small mt-1">
                <span class="badge text-bg-light border text-wrap text-start">
                  <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>Mesmas cores que:
                  <?= htmlspecialchars(implode(', ', $conflito)) ?>
                </span>
              </div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($p['usuario_moodle'])): ?>
                <code><?= htmlspecialchars($p['usuario_moodle']) ?></code>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <span class="badge <?= $p['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $p['ativo'] ? 'Ativo' : 'Inativo' ?>
              </span>
            </td>
            <td class="text-end">
              <a href="<?= $base ?>/professores/<?= $p['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="<?= $base ?>/professores/deletar" class="d-inline"
                    onsubmit="return confirm('Remover professor?')">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>
