<?php $pageTitle = 'Dashboard'; ?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['Cursos',       $stats['cursos'],      'bi-mortarboard-fill',  'bg-primary'],
    ['Professores',  $stats['professores'], 'bi-person-badge-fill', 'bg-success'],
    ['Turmas',       $stats['turmas'],      'bi-people-fill',       'bg-info'],
    ['Disciplinas',  $stats['disciplinas'], 'bi-book-fill',         'bg-warning'],
    ['Salas',        $stats['salas'],       'bi-door-open-fill',    'bg-secondary'],
    ['Horários',     $stats['semestres'],   'bi-calendar3',         'bg-dark'],
  ];
  foreach ($cards as [$label, $value, $icon, $bg]): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="<?= $bg ?> text-white rounded-3 p-2 fs-4">
          <i class="bi <?= $icon ?>"></i>
        </div>
        <div>
          <div class="fw-bold fs-4"><?= number_format((int)$value) ?></div>
          <div class="text-muted small"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- Semestres recentes -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar3 me-2 text-primary"></i>Horários</span>
        <a href="/horarios/novo" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-lg me-1"></i>Novo
        </a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($semestres)): ?>
        <div class="text-center text-muted py-5">
          <i class="bi bi-calendar-x display-6 d-block mb-2"></i>
          Nenhum horário cadastrado ainda.
          <div class="mt-3">
            <a href="/horarios/novo" class="btn btn-primary btn-sm">
              <i class="bi bi-plus-lg me-1"></i>Criar Horário
            </a>
          </div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Semestre</th>
                <th class="text-center">Horário</th>
                <th class="text-center">Agendadas</th>
                <th class="text-center">Falhas</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($semestres as $s): ?>
            <tr>
              <td class="fw-semibold"><?= $s['semestre'] ?>º Semestre / <?= $s['ano'] ?></td>
              <td class="text-center">
                <?php if ($s['geracao_status']): ?>
                <span class="badge <?= match($s['geracao_status']) {
                  'concluido'   => 'bg-success',
                  'parcial'     => 'bg-warning text-dark',
                  'erro'        => 'bg-danger',
                  'processando' => 'bg-info text-dark',
                  default       => 'bg-secondary'
                } ?>"><?= ucfirst($s['geracao_status']) ?></span>
                <?php else: ?>
                <span class="badge bg-light text-muted border">Não gerado</span>
                <?php endif; ?>
              </td>
              <td class="text-center text-success fw-semibold">
                <?= $s['atividades_agendadas'] ?? '—' ?>
              </td>
              <td class="text-center <?= ($s['atividades_falhas'] ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                <?= $s['atividades_falhas'] ?? '—' ?>
              </td>
              <td class="text-end">
                <a href="/horarios" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-arrow-right"></i>
                </a>
                <?php if ($s['geracao_id'] && in_array($s['geracao_status'], ['concluido','parcial'])): ?>
                <a href="/horarios/geracao/<?= $s['geracao_id'] ?>/grade" class="btn btn-sm btn-outline-dark" title="Grade">
                  <i class="bi bi-grid-3x3-gap"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Início rápido -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-lightning-fill me-2 text-warning"></i>Início Rápido
      </div>
      <div class="card-body p-0">
        <?php
        $steps = [
          ['/cursos/novo',      'bi-mortarboard',   'text-primary',  'Cadastrar Curso'],
          ['/professores/novo', 'bi-person-badge',  'text-success',  'Cadastrar Professor'],
          ['/turmas/nova',      'bi-people',        'text-info',     'Criar Turma'],
          ['/disciplinas/nova', 'bi-book',          'text-warning',  'Adicionar Disciplina'],
          ['/salas/nova',       'bi-door-open',     'text-secondary','Cadastrar Sala'],
          ['/horarios/novo',    'bi-calendar3',     'text-primary',  'Criar Horário'],
        ];
        foreach ($steps as $i => [$url, $icon, $color, $title]): ?>
        <a href="<?= $url ?>" class="d-flex align-items-center gap-3 px-3 py-2 text-decoration-none border-bottom quick-step">
          <span class="badge rounded-pill bg-light text-dark border fw-semibold" style="width:24px;height:24px;line-height:16px;font-size:11px"><?= $i+1 ?></span>
          <i class="bi <?= $icon ?> <?= $color ?> fs-5"></i>
          <span class="small fw-semibold text-dark"><?= $title ?></span>
          <i class="bi bi-chevron-right text-muted ms-auto small"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<style>
.quick-step:hover { background:#f8fafc; }
.quick-step:last-child { border-bottom: 0 !important; }
</style>
