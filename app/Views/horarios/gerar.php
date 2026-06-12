<?php
$pageTitle    = 'Geração de Horário';
$semestreLabel = $semestre['semestre'] . 'º Semestre / ' . $semestre['ano'];
$geracao      = $geracoes[0] ?? null;
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/horarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h5 class="mb-0 fw-semibold"><i class="bi bi-magic me-2 text-primary"></i>Geração de Horário</h5>
    <small class="text-muted"><?= $semestreLabel ?></small>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Formulário de geração -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-transparent fw-semibold">
    <i class="bi bi-sliders me-2 text-primary"></i>Gerar Horário
  </div>
  <div class="card-body">
    <?php if ($semAtribuir > 0): ?>
    <div class="alert alert-warning py-2 mb-3">
      <i class="bi bi-exclamation-triangle me-1"></i>
      <?= $semAtribuir ?> disciplina(s) sem professor — serão ignoradas na geração.
    </div>
    <?php endif; ?>
    <form method="POST" action="/horarios/<?= $semestreId ?>/gerar">
      <div class="row g-3">
        <div class="col-auto">
          <button type="submit" class="btn btn-primary"
                  onclick="return confirm('<?= $geracao ? 'Isso substituirá o horário atual. Continuar?' : 'Gerar horário?' ?>')">
            <i class="bi bi-magic me-1"></i><?= $geracao ? 'Regerar Horário' : 'Gerar Horário' ?>
          </button>
        </div>
        <div class="col-12">
          <div class="accordion accordion-flush" id="accordionPesos">
            <div class="accordion-item border rounded">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed py-2 small" type="button"
                        data-bs-toggle="collapse" data-bs-target="#colapsoPesos">
                  <i class="bi bi-sliders me-2"></i>Ajustar pesos das restrições (avançado)
                </button>
              </h2>
              <div id="colapsoPesos" class="accordion-collapse collapse">
                <div class="accordion-body pt-2">
                  <div class="row g-2">
                  <?php foreach ($pesos as $p): ?>
                  <div class="col-md-4">
                    <label class="form-label small mb-1"><?= htmlspecialchars($p['nome']) ?></label>
                    <input type="number" name="pesos[<?= $p['chave'] ?>]"
                           class="form-control form-control-sm"
                           value="<?= number_format((float)$p['valor'], 1) ?>"
                           step="0.5" min="0" max="10000">
                  </div>
                  <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Horário atual -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="bi bi-calendar-check me-2 text-primary"></i>Horário Atual</span>
    <?php if ($geracao): ?>
    <span class="badge <?= match($geracao['status']) {
      'concluido'   => 'bg-success',
      'parcial'     => 'bg-warning text-dark',
      'erro'        => 'bg-danger',
      'processando' => 'bg-info text-dark',
      default       => 'bg-secondary'
    } ?>"><?= ucfirst($geracao['status']) ?></span>
    <?php endif; ?>
  </div>
  <div class="card-body">
  <?php if (!$geracao): ?>
    <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>Nenhum horário gerado ainda.</p>
  <?php else: ?>
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div>
        <div class="small text-muted">Gerado em <?= date('d/m/Y H:i', strtotime($geracao['created_at'])) ?></div>
        <div class="mt-1">
          <span class="text-success fw-semibold"><?= $geracao['atividades_agendadas'] ?> agendadas</span>
          <?php if ($geracao['atividades_falhas'] > 0): ?>
          <span class="text-danger ms-2"><?= $geracao['atividades_falhas'] ?> falhas</span>
          <?php endif; ?>
        </div>
      </div>
      <?php if (in_array($geracao['status'], ['concluido','parcial'])): ?>
      <div class="d-flex gap-2 ms-auto flex-wrap">
        <a href="/horarios/geracao/<?= $geracao['id'] ?>/grade" class="btn btn-sm btn-outline-dark">
          <i class="bi bi-grid-3x3-gap me-1"></i>Grade
        </a>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="/horarios/geracao/<?= $geracao['id'] ?>/exportar/csv"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
            <li><a class="dropdown-item" href="/horarios/geracao/<?= $geracao['id'] ?>/exportar/excel"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
            <li><a class="dropdown-item" href="/horarios/geracao/<?= $geracao['id'] ?>/exportar/pdf" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>PDF / Imprimir</a></li>
          </ul>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($geracao['atividades_falhas'] > 0): ?>
      <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalLog">
        <i class="bi bi-exclamation-triangle me-1"></i>Ver conflitos
      </button>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  </div>
</div>

<?php if ($geracao && $geracao['atividades_falhas'] > 0): ?>
<div class="modal fade" id="modalLog" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header bg-warning">
      <h6 class="modal-title">Conflitos</h6>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <pre class="small mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($geracao['log'] ?? 'Sem detalhes.') ?></pre>
    </div>
  </div></div>
</div>
<?php endif; ?>
