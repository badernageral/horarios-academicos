<?php
$pageTitle = $professor ? 'Editar Professor' : 'Novo Professor';
$diasSemana = $config['dias_semana'] ?? [];
$dispExist  = $professor['disponibilidade'] ?? [];

?>

<div class="d-flex align-items-center mb-3">
  <a href="/professores" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><?= $pageTitle ?></h5>
</div>

<form method="POST" action="/professores/salvar">
  <?php if ($professor): ?>
  <input type="hidden" name="id" value="<?= $professor['id'] ?>">
  <?php endif; ?>

  <div class="row g-3">
    <!-- Dados básicos -->
    <div class="col-md-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Dados do Professor</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label">Nome <span class="text-danger">*</span></label>
              <input type="text" name="nome" class="form-control"
                     value="<?= htmlspecialchars($professor['nome'] ?? '') ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Cor</label>
              <input type="color" name="cor" class="form-control form-control-color w-100"
                     value="<?= htmlspecialchars($professor['cor'] ?? '#3b82f6') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="ativo" class="form-select">
                <option value="1" <?= ($professor['ativo'] ?? 1) == 1 ? 'selected':'' ?>>Ativo</option>
                <option value="0" <?= ($professor['ativo'] ?? 1) == 0 ? 'selected':'' ?>>Inativo</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Disponibilidade semanal -->
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
          Disponibilidade Semanal
          <button type="button" class="btn btn-sm btn-outline-success" id="btnAddDisp">
            <i class="bi bi-plus-lg"></i> Adicionar
          </button>
        </div>
        <div class="card-body">
          <div class="mb-2 small text-muted">
            Defina os dias e horários em que o professor está disponível.
          </div>
          <div id="disponibilidades">
            <?php if (empty($dispExist)): ?>
            <?php for ($d = 1; $d <= 5; $d++): ?>
            <div class="disponibilidade-row row g-2 align-items-center mb-2">
              <div class="col-md-3">
                <select name="disp_dia[]" class="form-select form-select-sm">
                  <?php foreach ($diasSemana as $num => $nome): ?>
                  <option value="<?= $num ?>" <?= $num == $d ? 'selected':'' ?>><?= $nome ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <input type="time" name="disp_inicio[]" class="form-control form-control-sm" value="07:00">
              </div>
              <div class="col-auto"><span class="text-muted">até</span></div>
              <div class="col-md-3">
                <input type="time" name="disp_fim[]" class="form-control form-control-sm" value="23:00">
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-danger remove-disp"><i class="bi bi-trash"></i></button>
              </div>
            </div>
            <?php endfor; ?>
            <?php else: ?>
            <?php foreach ($dispExist as $d): ?>
            <div class="disponibilidade-row row g-2 align-items-center mb-2">
              <div class="col-md-3">
                <select name="disp_dia[]" class="form-select form-select-sm">
                  <?php foreach ($diasSemana as $num => $nome): ?>
                  <option value="<?= $num ?>" <?= $num == $d['dia_semana'] ? 'selected':'' ?>><?= $nome ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <input type="time" name="disp_inicio[]" class="form-control form-control-sm"
                       value="<?= substr($d['hora_inicio'], 0, 5) ?>">
              </div>
              <div class="col-auto"><span class="text-muted">até</span></div>
              <div class="col-md-3">
                <input type="time" name="disp_fim[]" class="form-control form-control-sm"
                       value="<?= substr($d['hora_fim'], 0, 5) ?>">
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-danger remove-disp"><i class="bi bi-trash"></i></button>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Salvar
      </button>
      <a href="/professores" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </div>
</form>

<script>
const diasOptions = <?= json_encode($diasSemana) ?>;

document.getElementById('btnAddDisp').addEventListener('click', function() {
  const container = document.getElementById('disponibilidades');
  const div = document.createElement('div');
  div.className = 'disponibilidade-row row g-2 align-items-center mb-2';
  let opts = '';
  for (const [num, nome] of Object.entries(diasOptions)) {
    opts += `<option value="${num}">${nome}</option>`;
  }
  div.innerHTML = `
    <div class="col-md-3"><select name="disp_dia[]" class="form-select form-select-sm">${opts}</select></div>
    <div class="col-md-3"><input type="time" name="disp_inicio[]" class="form-control form-control-sm" value="07:00"></div>
    <div class="col-auto"><span class="text-muted">até</span></div>
    <div class="col-md-3"><input type="time" name="disp_fim[]" class="form-control form-control-sm" value="23:00"></div>
    <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger remove-disp"><i class="bi bi-trash"></i></button></div>
  `;
  container.appendChild(div);
});

document.getElementById('disponibilidades').addEventListener('click', function(e) {
  if (e.target.closest('.remove-disp')) {
    e.target.closest('.disponibilidade-row').remove();
  }
});
</script>
