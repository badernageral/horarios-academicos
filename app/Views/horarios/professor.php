<?php
$pageTitle = 'Horário por Professor';
$corProf = fn($h) => !empty($h['professor_cor']) ? $h['professor_cor'] : '#94a3b8';
$diasAbrev  = $config['dias_semana_abrev'] ?? [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb'];
$diasNomes  = $config['dias_semana'] ?? [1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado'];
$pxPerMin   = 1.6;

// Calcular turno a partir dos horários disponíveis
$turnoInicio = 420; // 07:00 padrão
$turnoFim    = 1260; // 21:00 padrão
if (!empty($horarios)) {
    $minInicio = min(array_map(fn($h) => \App\Services\TimeHelper::toMinutes($h['hora_inicio']), $horarios));
    $maxFim    = max(array_map(fn($h) => \App\Services\TimeHelper::toMinutes($h['hora_fim']), $horarios));
    $turnoInicio = max(360, $minInicio - 30);
    $turnoFim    = min(1380, $maxFim + 30);
}
$totalMin = $turnoFim - $turnoInicio;

$porDia = [];
foreach ($horarios as $h) $porDia[$h['dia_semana']][] = $h;
$diasPresentes = array_keys($porDia);
sort($diasPresentes);
if (empty($diasPresentes)) $diasPresentes = [1,2,3,4,5];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/horarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-success"></i>Horário por Professor</h5>
  <span class="badge bg-secondary ms-auto">Geração #<?= $geracaoId ?></span>
</div>

<div class="row g-2 mb-3 align-items-end">
  <div class="col-md-4">
    <label class="form-label small mb-1">Professor</label>
    <select class="form-select form-select-sm"
            onchange="location='/horarios/geracao/<?= $geracaoId ?>/professor?professor_id='+this.value">
      <?php foreach ($professores as $p): ?>
      <option value="<?= $p['id'] ?>" <?= $p['id'] == $profId ? 'selected':'' ?>>
        <?= htmlspecialchars($p['nome']) ?> (<?= $p['matricula'] ?>)
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  
</div>

<?php if (empty($horarios)): ?>
<div class="alert alert-info">Nenhum horário para este professor nesta geração.</div>
<?php else: ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-transparent fw-semibold d-flex justify-content-between">
    <span><?= $professor ? htmlspecialchars($professor['nome']) : '' ?></span>
    <small class="text-muted"><?= count($horarios) ?> aulas – <?= \App\Services\TimeHelper::formatDuration(array_sum(array_map(fn($h) => \App\Services\TimeHelper::duration($h['hora_inicio'], $h['hora_fim']), $horarios))) ?>/sem</small>
  </div>
  <div class="card-body p-2 overflow-auto">
    <div class="schedule-grid" style="display:flex; gap:4px;">
      <!-- Eixo de tempo -->
      <div style="width:44px; flex-shrink:0; position:relative; height:<?= $totalMin * $pxPerMin ?>px; margin-top:28px">
        <?php for ($m = 0; $m <= $totalMin; $m += 30):
          $top = $m * $pxPerMin;
          $label = \App\Services\TimeHelper::fromMinutes($turnoInicio + $m);
        ?>
        <div style="position:absolute; top:<?= $top ?>px; right:4px; font-size:10px; color:#888; white-space:nowrap; transform:translateY(-50%)"><?= $label ?></div>
        <div style="position:absolute; top:<?= $top ?>px; right:0; left:38px; border-top:1px dashed #ddd;"></div>
        <?php endfor; ?>
      </div>

      <?php foreach ($diasPresentes as $dia): ?>
      <div style="flex:1; min-width:120px; position:relative;">
        <div style="height:28px; text-align:center; font-size:12px; font-weight:600; color:#444; border-bottom:2px solid #e2e8f0; padding-top:6px;">
          <?= $diasAbrev[$dia] ?? $dia ?>
        </div>
        <div style="position:relative; height:<?= $totalMin * $pxPerMin ?>px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;">
          <?php for ($m = 0; $m <= $totalMin; $m += 30): ?>
          <div style="position:absolute; top:<?= $m * $pxPerMin ?>px; left:0; right:0; border-top:1px dashed #e2e8f0;"></div>
          <?php endfor; ?>

          <?php foreach ($porDia[$dia] ?? [] as $h):
            $hI   = \App\Services\TimeHelper::toMinutes($h['hora_inicio']);
            $hF   = \App\Services\TimeHelper::toMinutes($h['hora_fim']);
            $top  = ($hI - $turnoInicio) * $pxPerMin;
            $hght = ($hF - $hI) * $pxPerMin;
            $cor  = $corProf($h);
          ?>
          <div class="aula-block"
               style="position:absolute; top:<?= $top ?>px; left:2px; right:2px;
                      height:<?= max($hght, 18) ?>px; background:<?= $cor ?>30;
                      border-left:3px solid <?= $cor ?>; border-radius:3px;
                      padding:2px 4px; overflow:hidden;"
               data-bs-toggle="tooltip"
               title="<?= htmlspecialchars($h['disciplina_nome'] . ' – ' . $h['turma_nome'] . "\n" . substr($h['hora_inicio'],0,5) . '–' . substr($h['hora_fim'],0,5) . "\nSala: " . ($h['sala_nome'] ?? '—')) ?>">
            <div style="font-size:10px; font-weight:600; color:<?= $cor ?>; line-height:1.2">
              <?= htmlspecialchars($h['disciplina_sigla'] ?: mb_substr($h['disciplina_nome'], 0, 16)) ?>
            </div>
            <?php if ($hght > 22): ?>
            <div style="font-size:9px; color:#555;">
              <?= htmlspecialchars($h['turma_nome']) ?>
            </div>
            <?php endif; ?>
            <?php if ($hght > 38): ?>
            <div style="font-size:9px; color:#888;">
              <?= substr($h['hora_inicio'],0,5) ?>–<?= substr($h['hora_fim'],0,5) ?>
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
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr><th>Dia</th><th>Início</th><th>Fim</th><th>Duração</th><th>Disciplina</th><th>Turma</th><th>Sala</th></tr>
        </thead>
        <tbody>
        <?php
        usort($horarios, fn($a,$b) =>
          $a['dia_semana'] !== $b['dia_semana'] ? $a['dia_semana']-$b['dia_semana'] : strcmp($a['hora_inicio'],$b['hora_inicio'])
        );
        foreach ($horarios as $h):
          $dur = \App\Services\TimeHelper::duration($h['hora_inicio'], $h['hora_fim']);
        ?>
        <tr>
          <td><?= $diasNomes[$h['dia_semana']] ?? '' ?></td>
          <td><?= substr($h['hora_inicio'],0,5) ?></td>
          <td><?= substr($h['hora_fim'],0,5) ?></td>
          <td><span class="badge bg-info text-dark"><?= \App\Services\TimeHelper::formatDuration($dur) ?></span></td>
          <td>
            <span style="background:<?= $corProf($h) ?>;width:10px;height:10px;border-radius:50%;display:inline-block;"></span>
            <?= htmlspecialchars($h['disciplina_nome']) ?>
          </td>
          <td><?= htmlspecialchars($h['turma_nome']) ?></td>
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
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
