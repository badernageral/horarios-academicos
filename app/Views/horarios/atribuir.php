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
  <?php if ($semAtribuir > 0): ?>
  <span class="badge bg-warning text-dark ms-auto"><?= $semAtribuir ?> sem professor</span>
  <?php else: ?>
  <span class="badge bg-success ms-auto"><i class="bi bi-check-lg me-1"></i>Todos atribuídos</span>
  <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
            <th style="min-width:200px">Professor</th>
            <th style="min-width:180px">Sala</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($disciplinas as $d):
          $durEncontro = (int)$d['qtd_aulas'] * (int)$d['duracao_aula_minutos'];
        ?>
          <tr>
            <td><?= htmlspecialchars($d['nome']) ?></td>
            <td class="small"><?= htmlspecialchars($d['curso_nome'] . ' – ' . $d['turma_nome']) ?></td>
            <td class="text-center"><span class="badge bg-primary"><?= $d['qtd_encontros_semanais'] ?>×</span></td>
            <td class="text-center"><span class="badge bg-secondary"><?= \App\Services\TimeHelper::formatDuration($durEncontro) ?></span></td>
            <td>
              <select name="atribuicao[<?= $d['id'] ?>]" class="form-select form-select-sm">
                <option value="">— Sem professor —</option>
                <?php foreach ($professores as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $d['professor_atribuido'] == $p['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['nome']) ?>
                </option>
                <?php endforeach; ?>
              </select>
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
