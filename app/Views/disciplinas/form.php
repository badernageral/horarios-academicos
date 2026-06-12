<?php
$pageTitle = $disciplina ? 'Editar Disciplina' : 'Nova Disciplina';
$coresDisc = $config['cores_disciplinas'] ?? [];
?>

<div class="d-flex align-items-center mb-3">
  <a href="/disciplinas" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><?= $pageTitle ?></h5>
</div>

<form method="POST" action="/disciplinas/salvar">
  <?php if ($disciplina): ?>
  <input type="hidden" name="id" value="<?= $disciplina['id'] ?>">
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Dados da Disciplina</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Nome <span class="text-danger">*</span></label>
              <input type="text" name="nome" class="form-control"
                     value="<?= htmlspecialchars($disciplina['nome'] ?? '') ?>"
                     placeholder="Ex: Algoritmos" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Sigla <span class="text-danger">*</span></label>
              <input type="text" name="sigla" class="form-control"
                     value="<?= htmlspecialchars($disciplina['sigla'] ?? '') ?>"
                     placeholder="Ex: ALG" maxlength="20" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Turma <span class="text-danger">*</span></label>
              <select name="turma_id" id="turma_id" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($turmas as $t): ?>
                <option value="<?= $t['id'] ?>"
                        data-curso="<?= $t['curso_id'] ?>"
                        data-duracao="<?= (int)$t['duracao_aula_minutos'] ?>"
                        <?= ($disciplina['turma_id'] ?? '') == $t['id'] ? 'selected':'' ?>>
                  <?= htmlspecialchars($t['curso_nome'] . ' – ' . $t['serie_periodo']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Oferta <span class="text-danger">*</span></label>
              <?php $oferta = (int)($disciplina['semestre_oferta'] ?? 3); ?>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="semestre_1" id="semestre_1" value="1"
                         <?= ($oferta & 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="semestre_1">1º Semestre</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="semestre_2" id="semestre_2" value="1"
                         <?= ($oferta & 2) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="semestre_2">2º Semestre</label>
                </div>
              </div>
              <div class="form-text">Marque ambos para disciplinas anuais.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Carga Horária</div>
        <div class="card-body">
          <div class="alert alert-info small py-2 px-3 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Horários em <strong>tempo real (minutos)</strong> — sem períodos fixos.
          </div>

          <div class="mb-3">
            <label class="form-label">Encontros Semanais <span class="text-danger">*</span></label>
            <input type="number" name="qtd_encontros_semanais" id="qtdEncontros"
                   class="form-control" min="1" max="20"
                   value="<?= (int)($disciplina['qtd_encontros_semanais'] ?? 1) ?>" required>
            <div class="form-text">Quantas vezes por semana a disciplina ocorre</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Aulas por Encontro <span class="text-danger">*</span></label>
            <input type="number" name="qtd_aulas" id="qtdAulas"
                   class="form-control" min="1" max="10"
                   value="<?= (int)($disciplina['qtd_aulas'] ?? 2) ?>" required>
            <div class="form-text" id="textoAula">
              <?php
                $durCurso = (int)($disciplina['duracao_aula_minutos'] ?? 0);
                echo $durCurso > 0
                  ? "Cada aula dura {$durCurso} min (definido no curso)"
                  : 'Selecione a turma para ver a duração da aula';
              ?>
            </div>
          </div>

          <div class="card bg-light border-0 p-2 text-center mb-3">
            <div class="small text-muted">Duração por encontro</div>
            <div class="fw-bold" id="duracaoEncontroDisplay">—</div>
            <div class="small text-muted mt-1">Total semanal</div>
            <div class="fw-bold fs-5" id="totalSemanal">—</div>
          </div>

          <div>
            <label class="form-label">Status</label>
            <select name="ativo" class="form-select">
              <option value="1" <?= ($disciplina['ativo'] ?? 1) == 1 ? 'selected':'' ?>>Ativa</option>
              <option value="0" <?= ($disciplina['ativo'] ?? 1) == 0 ? 'selected':'' ?>>Inativa</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Salvar
      </button>
      <a href="/disciplinas" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </div>
</form>

<script>
function formatDur(min) {
  if (!min) return '—';
  const h = Math.floor(min / 60), m = min % 60;
  return min < 60 ? `${min}min` : (m > 0 ? `${h}h${m}min` : `${h}h`);
}

function recalcularTotal() {
  const enc      = parseInt(document.getElementById('qtdEncontros').value) || 0;
  const aulas    = parseInt(document.getElementById('qtdAulas').value) || 0;
  const sel      = document.querySelector('#turma_id option:checked');
  const durAula  = sel ? (parseInt(sel.dataset.duracao) || 0) : 0;
  const durEnc   = aulas * durAula;
  const total    = enc * durEnc;
  document.getElementById('duracaoEncontroDisplay').textContent = formatDur(durEnc);
  document.getElementById('totalSemanal').textContent           = formatDur(total);
  if (durAula > 0) {
    document.getElementById('textoAula').textContent = `Cada aula dura ${durAula} min (definido no curso)`;
  }
}

document.getElementById('qtdEncontros').addEventListener('input', recalcularTotal);
document.getElementById('qtdAulas').addEventListener('input', recalcularTotal);
document.getElementById('turma_id').addEventListener('change', recalcularTotal);
recalcularTotal();
</script>
