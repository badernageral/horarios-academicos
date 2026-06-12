<?php $pageTitle = 'Horários'; ?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0 fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i>Horários por Semestre</h5>
  <a href="/horarios/novo" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Novo Semestre
  </a>
</div>

<?php if (empty($semestres)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-calendar-x display-5 d-block mb-3"></i>
    Nenhum semestre cadastrado ainda.<br>
    <a href="/horarios/novo" class="btn btn-primary mt-3">
      <i class="bi bi-plus-lg me-1"></i>Criar Primeiro Semestre
    </a>
  </div>
</div>
<?php else: ?>

<div class="d-flex flex-column gap-3">
<?php foreach ($semestres as $s):
  $ger = $s['ultima_geracao'];
  $gerado = $ger && in_array($ger['status'], ['concluido', 'parcial']);
?>
<div class="card border-0 shadow-sm">
  <div class="card-body">

    <!-- Cabeçalho: título + badges + ações de admin -->
    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
      <span class="fw-semibold fs-6"><?= $s['semestre'] ?>º Semestre / <?= $s['ano'] ?></span>

      <?php if ($s['qtd_atribuicoes'] > 0): ?>
        <span class="badge bg-success"><i class="bi bi-person-check me-1"></i><?= $s['qtd_atribuicoes'] ?> atribuições</span>
      <?php else: ?>
        <span class="badge bg-warning text-dark">Sem atribuições</span>
      <?php endif; ?>

      <?php if ($ger): ?>
        <span class="badge <?= match($ger['status']) {
          'concluido'   => 'bg-success',
          'parcial'     => 'bg-warning text-dark',
          'erro'        => 'bg-danger',
          'processando' => 'bg-info text-dark',
          default       => 'bg-secondary'
        } ?>"><?= ucfirst($ger['status']) ?></span>
        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($ger['created_at'])) ?></small>
      <?php else: ?>
        <span class="badge bg-light text-muted border">Não gerado</span>
      <?php endif; ?>

      <div class="ms-auto d-flex gap-1">
        <a href="/horarios/<?= $s['id'] ?>/editar" class="btn btn-sm btn-outline-secondary" title="Editar semestre">
          <i class="bi bi-pencil"></i>
        </a>
        <form method="POST" action="/horarios/deletar" class="d-inline"
              onsubmit="return confirm('Remover semestre e todas as gerações?')">
          <input type="hidden" name="id" value="<?= $s['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>

    <!-- Ações principais -->
    <div class="d-flex flex-wrap gap-2">

      <!-- Atribuir -->
      <a href="/horarios/<?= $s['id'] ?>/atribuir" class="btn btn-sm btn-success">
        <i class="bi bi-person-badge me-1"></i>Professores e Salas
      </a>

      <!-- Gerar -->
      <form method="POST" action="/horarios/<?= $s['id'] ?>/gerar" class="d-inline"
            onsubmit="return confirm('<?= $gerado ? 'Regerar horário? O atual será substituído.' : 'Gerar horário agora?' ?>')">
        <button class="btn btn-sm <?= $gerado ? 'btn-outline-primary' : 'btn-primary' ?>">
          <i class="bi bi-magic me-1"></i><?= $gerado ? 'Regerar' : 'Gerar Horário' ?>
        </button>
      </form>

      <?php if ($gerado): ?>
      <!-- Visualizar -->
      <a href="/horarios/geracao/<?= $ger['id'] ?>/grade" class="btn btn-sm btn-outline-dark">
        <i class="bi bi-grid-3x3-gap me-1"></i>Grade
      </a>
      <a href="/horarios/geracao/<?= $ger['id'] ?>/turma" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-people me-1"></i>Por Turma
      </a>
      <a href="/horarios/geracao/<?= $ger['id'] ?>/professor" class="btn btn-sm btn-outline-success">
        <i class="bi bi-person-badge me-1"></i>Por Professor
      </a>
      <a href="/horarios/geracao/<?= $ger['id'] ?>/sala" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-door-open me-1"></i>Por Sala
      </a>

      <!-- Exportar -->
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown">
          <i class="bi bi-download me-1"></i>Exportar
        </button>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="/horarios/geracao/<?= $ger['id'] ?>/exportar/csv"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
          <li><a class="dropdown-item" href="/horarios/geracao/<?= $ger['id'] ?>/exportar/excel"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
          <li><a class="dropdown-item" href="/horarios/geracao/<?= $ger['id'] ?>/exportar/pdf" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>PDF / Imprimir</a></li>
        </ul>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>
