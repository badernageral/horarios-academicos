<?php
$pageTitle = 'Horário por Turma';
$corProf = fn($h) => !empty($h['professor_cor']) ? $h['professor_cor'] : '#94a3b8';
$diasNomes  = $config['dias_semana'] ?? [1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado'];
$diasAbrev  = $config['dias_semana_abrev'] ?? [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb'];

// Calcular turno do curso para a timeline
$turnoInicio = $turma ? \App\Services\TimeHelper::toMinutes($turma['turno_inicio']) : 420;
$turnoFim    = $turma ? \App\Services\TimeHelper::toMinutes($turma['turno_fim'])    : 720;
$totalMin    = max($turnoFim - $turnoInicio, 1);
$pxPerMin    = 1.6; // pixels por minuto

// Agrupar horários por dia
$porDia = [];
foreach ($horarios as $h) {
    $porDia[$h['dia_semana']][] = $h;
}
$diasPresentes = $turma
    ? array_map('intval', json_decode($turma['dias_semana'], true) ?? [1,2,3,4,5])
    : array_keys($porDia);
sort($diasPresentes);
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/horarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Horário por Turma</h5>
  <span class="badge bg-secondary ms-auto">Geração #<?= $geracaoId ?> – <?= htmlspecialchars($geracao['descricao'] ?? '') ?></span>
</div>

<div class="row g-2 mb-3 align-items-end">
  <div class="col-md-4">
    <label class="form-label small mb-1">Turma</label>
    <select class="form-select form-select-sm" onchange="location='/horarios/geracao/<?= $geracaoId ?>/turma?turma_id='+this.value">
      <?php foreach ($turmas as $t): ?>
      <option value="<?= $t['id'] ?>" <?= $t['id'] == $turmaId ? 'selected':'' ?>>
        <?= htmlspecialchars($t['curso_nome'] . ' – ' . $t['serie_periodo']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <div class="btn-group btn-group-sm">
      <a href="/horarios/geracao/<?= $geracaoId ?>/exportar/csv" class="btn btn-outline-secondary">
        <i class="bi bi-filetype-csv"></i> CSV
      </a>
      <a href="/horarios/geracao/<?= $geracaoId ?>/exportar/excel" class="btn btn-outline-secondary">
        <i class="bi bi-file-earmark-excel"></i> Excel
      </a>
      <a href="/horarios/geracao/<?= $geracaoId ?>/exportar/pdf" target="_blank" class="btn btn-outline-secondary">
        <i class="bi bi-printer"></i> PDF
      </a>
    </div>
  </div>
</div>

<?php if (empty($horarios)): ?>
<div class="alert alert-info">Nenhum horário gerado para esta turma nesta geração.</div>
<?php else: ?>

<!-- Timeline visual (sem períodos fixos — posicionamento por tempo real) -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
    <span>
      <?= $turma ? htmlspecialchars($turma['curso_nome'] . ' – ' . $turma['serie_periodo']) : '' ?>
    </span>
    <small class="text-muted">Turno: <?= \App\Services\TimeHelper::fromMinutes($turnoInicio) ?>–<?= \App\Services\TimeHelper::fromMinutes($turnoFim) ?></small>
  </div>
  <div class="card-body p-2 overflow-auto">
    <div class="schedule-grid" style="display:flex; gap:4px;">

      <!-- Eixo de tempo (Y) -->
      <div class="time-axis" style="width:44px; flex-shrink:0; position:relative; height:<?= $totalMin * $pxPerMin ?>px; margin-top:28px">
        <?php
        // Marca a cada 30 minutos
        for ($m = 0; $m <= $totalMin; $m += 30):
          $top = $m * $pxPerMin;
          $timeLabel = \App\Services\TimeHelper::fromMinutes($turnoInicio + $m);
        ?>
        <div style="position:absolute; top:<?= $top ?>px; right:4px; font-size:10px; color:#888; white-space:nowrap; transform:translateY(-50%)">
          <?= $timeLabel ?>
        </div>
        <div style="position:absolute; top:<?= $top ?>px; right:0; left:38px; border-top:1px dashed #ddd;"></div>
        <?php endfor; ?>
      </div>

      <!-- Colunas por dia -->
      <?php foreach ($diasPresentes as $dia): ?>
      <div class="day-column" style="flex:1; min-width:120px; position:relative;">
        <!-- Header do dia -->
        <div style="height:28px; text-align:center; font-size:12px; font-weight:600; color:#444; border-bottom:2px solid #e2e8f0; padding-top:6px;">
          <?= $diasAbrev[$dia] ?? $dia ?>
        </div>
        <!-- Container da timeline -->
        <div style="position:relative; height:<?= $totalMin * $pxPerMin ?>px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;">

          <!-- Linhas de grade a cada 30min -->
          <?php for ($m = 0; $m <= $totalMin; $m += 30): ?>
          <div style="position:absolute; top:<?= $m * $pxPerMin ?>px; left:0; right:0; border-top:1px dashed #e2e8f0;"></div>
          <?php endfor; ?>

          <!-- Bloco de cada aula -->
          <?php foreach ($porDia[$dia] ?? [] as $h):
            $hInicio  = \App\Services\TimeHelper::toMinutes($h['hora_inicio']);
            $hFim     = \App\Services\TimeHelper::toMinutes($h['hora_fim']);
            $top      = ($hInicio - $turnoInicio) * $pxPerMin;
            $height   = ($hFim - $hInicio) * $pxPerMin;
            $cor      = $corProf($h);
            $corLight = $cor . '30';
          ?>
          <div class="aula-block"
               style="position:absolute; top:<?= $top ?>px; left:2px; right:2px;
                      height:<?= max($height, 20) ?>px; background:<?= $corLight ?>;
                      border-left:3px solid <?= $cor ?>; border-radius:3px;
                      padding:2px 4px; overflow:hidden; cursor:pointer;"
               data-bs-toggle="tooltip"
               title="<?= htmlspecialchars($h['disciplina_nome'] . "\n" . substr($h['hora_inicio'],0,5) . '–' . substr($h['hora_fim'],0,5) . "\nProf: " . $h['professor_nome'] . "\nSala: " . ($h['sala_nome'] ?? 'Sem sala')) ?>">
            <div style="font-size:10px; font-weight:600; color:<?= $cor ?>; line-height:1.2;">
              <?= htmlspecialchars($h['disciplina_sigla'] ?: mb_substr($h['disciplina_nome'], 0, 18)) ?>
            </div>
            <?php if ($height > 22): ?>
            <div style="font-size:9px; color:#666; line-height:1.1;">
              <?= substr($h['hora_inicio'],0,5) ?>–<?= substr($h['hora_fim'],0,5) ?>
            </div>
            <?php endif; ?>
            <?php if ($height > 38): ?>
            <div style="font-size:9px; color:#888; line-height:1.1;">
              <?= htmlspecialchars(mb_substr($h['professor_nome'], 0, 16)) ?>
            </div>
            <?php endif; ?>
            <?php if ($height > 54 && $h['sala_nome']): ?>
            <div style="font-size:9px; color:#888; line-height:1.1;">
              <?= htmlspecialchars($h['sala_nome']) ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Tabela detalhada -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-transparent fw-semibold">Detalhamento</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Dia</th><th>Início</th><th>Fim</th><th>Duração</th>
            <th>Disciplina</th><th>Professor</th><th>Sala</th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Ordenar por dia e hora
        usort($horarios, fn($a, $b) =>
          $a['dia_semana'] !== $b['dia_semana']
            ? $a['dia_semana'] - $b['dia_semana']
            : strcmp($a['hora_inicio'], $b['hora_inicio'])
        );
        foreach ($horarios as $h):
          $dur = \App\Services\TimeHelper::duration($h['hora_inicio'], $h['hora_fim']);
        ?>
        <tr>
          <td><?= $diasNomes[$h['dia_semana']] ?? $h['dia_semana'] ?></td>
          <td><?= substr($h['hora_inicio'],0,5) ?></td>
          <td><?= substr($h['hora_fim'],0,5) ?></td>
          <td><span class="badge bg-info text-dark"><?= \App\Services\TimeHelper::formatDuration($dur) ?></span></td>
          <td>
            <span class="color-dot me-1" style="background:<?= $corProf($h) ?>;
              width:10px;height:10px;border-radius:50%;display:inline-block;"></span>
            <?= htmlspecialchars($h['disciplina_nome']) ?>
          </td>
          <td><?= htmlspecialchars($h['professor_nome']) ?></td>
          <td><?= htmlspecialchars($h['sala_nome'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php endif; ?>

<script>
// Inicializar tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
  new bootstrap.Tooltip(el, { html: false });
});
</script>
