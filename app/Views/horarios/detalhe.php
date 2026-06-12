<?php
$pageTitle    = $semestre['semestre'] . 'º Semestre / ' . $semestre['ano'];
$semestreLabel = $pageTitle;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/horarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i><?= $semestreLabel ?></h5>
  <a href="/horarios/<?= $semestreId ?>/editar" class="btn btn-sm btn-outline-secondary ms-auto">
    <i class="bi bi-pencil me-1"></i>Editar
  </a>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <!-- Atribuição -->
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex flex-column gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-success text-white rounded-3 p-2 fs-4">
            <i class="bi bi-person-badge-fill"></i>
          </div>
          <div>
            <div class="fw-semibold">Atribuição de Professores e Salas</div>
            <?php if ($semAtribuir > 0): ?>
            <div class="small text-warning fw-semibold">
              <i class="bi bi-exclamation-triangle me-1"></i><?= $semAtribuir ?> disciplina(s) sem professor
            </div>
            <?php else: ?>
            <div class="small text-success">
              <i class="bi bi-check-circle me-1"></i>Todos os professores atribuídos
            </div>
            <?php endif; ?>
          </div>
        </div>
        <a href="/horarios/<?= $semestreId ?>/atribuir" class="btn btn-success mt-auto">
          <i class="bi bi-person-badge me-1"></i>Atribuir Professores e Salas
        </a>
      </div>
    </div>
  </div>

  <!-- Geração -->
  <div class="col-md-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex flex-column gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-primary text-white rounded-3 p-2 fs-4">
            <i class="bi bi-magic"></i>
          </div>
          <div>
            <div class="fw-semibold">Geração de Horário</div>
            <?php if ($geracao): ?>
            <span class="badge <?= match($geracao['status']) {
              'concluido'   => 'bg-success',
              'parcial'     => 'bg-warning text-dark',
              'erro'        => 'bg-danger',
              'processando' => 'bg-info text-dark',
              default       => 'bg-secondary'
            } ?>"><?= ucfirst($geracao['status']) ?></span>
            <span class="small text-muted ms-1"><?= date('d/m/Y H:i', strtotime($geracao['created_at'])) ?></span>
            <?php else: ?>
            <div class="small text-muted">Nenhum horário gerado ainda</div>
            <?php endif; ?>
          </div>
        </div>
        <form method="POST" action="/horarios/<?= $semestreId ?>/gerar" class="mt-auto"
              onsubmit="return confirm('<?= $geracao ? 'Isso substituirá o horário atual. Continuar?' : 'Gerar horário agora?' ?>')">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-magic me-1"></i><?= $geracao ? 'Regerar Horário' : 'Gerar Horário' ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($geracao && in_array($geracao['status'], ['concluido','parcial'])): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-transparent fw-semibold">
    <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Visualizar Horário
  </div>
  <div class="card-body d-flex gap-2 flex-wrap">
    <a href="/horarios/geracao/<?= $geracao['id'] ?>/grade" class="btn btn-outline-dark">
      <i class="bi bi-grid-3x3-gap me-1"></i>Grade Completa
    </a>
    <div class="dropdown ms-auto">
      <button class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-download me-1"></i>Exportar
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="/horarios/geracao/<?= $geracao['id'] ?>/exportar/csv"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
        <li><a class="dropdown-item" href="/horarios/geracao/<?= $geracao['id'] ?>/exportar/excel"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
        <li><a class="dropdown-item" href="/horarios/geracao/<?= $geracao['id'] ?>/exportar/pdf" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>PDF / Imprimir</a></li>
      </ul>
    </div>
  </div>
</div>
<?php endif; ?>
