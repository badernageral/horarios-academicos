<?php
$pageTitle    = 'Atribuição de Professores e Salas';
$semestreLabel = $semestre['semestre'] . 'º Semestre / ' . $semestre['ano'];
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="/horarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h5 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-success"></i>Atribuição de Professores e Salas</h5>
    <small class="text-muted"><?= $semestreLabel ?></small>
  </div>
  <a href="/horarios/<?= $semestreId ?>/atribuir/importar" class="btn btn-sm btn-outline-success ms-auto">
    <i class="bi bi-cloud-upload me-1"></i>Importar em Massa
  </a>
  <?php if ($semAtribuir > 0): ?>
  <span class="badge bg-warning text-dark"><?= $semAtribuir ?> slot(s) sem professor</span>
  <?php else: ?>
  <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Professores OK</span>
  <?php endif; ?>
  <?php if ($semSala > 0): ?>
  <span class="badge bg-primary"><?= $semSala ?> sem sala</span>
  <?php else: ?>
  <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Salas OK</span>
  <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($avisos)): ?>
<div class="alert alert-danger">
  <div class="fw-semibold mb-1">
    <i class="bi bi-exclamation-octagon me-1"></i>Problemas de viabilidade — a geração não conseguirá agendar tudo:
  </div>
  <ul class="mb-0 small">
    <?php foreach ($avisos as $a): ?>
    <li><?= htmlspecialchars($a) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <form method="POST" action="/horarios/<?= $semestreId ?>/atribuir">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Disciplina</th>
            <th>Turma</th>
            <th class="text-center">Encontros</th>
            <th class="text-center">Duração</th>
            <th style="min-width:220px">Professores</th>
            <th style="min-width:180px">Sala</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($disciplinas as $d):
          $durEncontro  = (int)$d['qtd_aulas'] * (int)$d['duracao_aula_minutos'];
          $qtdProfs     = max(1, (int)($d['qtd_professores'] ?? 1));
          $atribuidos   = $d['professores_atribuidos'] ?? [];
          $semProf      = count($atribuidos) < $qtdProfs;
          $semSala      = empty($d['sala_atribuida']);
          $rowBg = $semProf
              ? '--bs-table-bg:#fff3cd;--bs-table-striped-bg:#fff3cd;'   // amarelo — sem professor
              : ($semSala
                  ? '--bs-table-bg:#dbeafe;--bs-table-striped-bg:#dbeafe;' // azul — sem sala
                  : '');
        ?>
          <tr <?= $rowBg ? "style=\"{$rowBg}\"" : '' ?>>
            <td>
              <?= htmlspecialchars($d['nome']) ?>
              <?php if ($qtdProfs > 1): ?>
              <span class="badge bg-info text-dark ms-1"><?= $qtdProfs ?> prof.</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= htmlspecialchars($d['curso_nome'] . ' – ' . $d['turma_nome']) ?></td>
            <td class="text-center"><span class="badge bg-primary"><?= $d['qtd_encontros_semanais'] ?>×</span></td>
            <td class="text-center"><span class="badge bg-secondary"><?= \App\Services\TimeHelper::formatDuration($durEncontro) ?></span></td>
            <td>
              <?php for ($slot = 1; $slot <= $qtdProfs; $slot++):
                $profAtrib = $atribuidos[$slot - 1] ?? '';
              ?>
              <div class="<?= $qtdProfs > 1 ? 'mb-1' : '' ?>">
                <?php if ($qtdProfs > 1): ?>
                <label class="form-label small text-muted mb-0">Prof. <?= $slot ?></label>
                <?php endif; ?>
                <select name="atribuicao[<?= $d['id'] ?>][<?= $slot ?>]" class="form-select form-select-sm">
                  <option value="">— Sem professor —</option>
                  <?php foreach ($professores as $p): ?>
                  <option value="<?= $p['id'] ?>" <?= $profAtrib == $p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nome']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endfor; ?>
            </td>
            <td>
              <select name="sala[<?= $d['id'] ?>]" class="form-select form-select-sm">
                <option value="">— Sem sala —</option>
                <?php foreach ($salas as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $d['sala_atribuida'] == $s['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($s['nome']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
      <button type="submit" class="btn btn-success">
        <i class="bi bi-check-lg me-1"></i>Salvar Atribuições
      </button>
      <a href="/horarios" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
</div>
