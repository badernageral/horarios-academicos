<?php
$pageTitle  = $curso ? 'Editar Curso' : 'Novo Curso';
$diasSemana = $config['dias_semana'] ?? [];
$diasCurso  = $curso ? (is_array($curso['dias_semana']) ? $curso['dias_semana'] : json_decode($curso['dias_semana'], true)) : [1,2,3,4,5];
$intervalos = $curso['intervalos'] ?? [];
?>

<div class="d-flex align-items-center mb-3">
  <a href="<?= $base ?>/cursos" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><?= $pageTitle ?></h5>
</div>

<form method="POST" action="<?= $base ?>/cursos/salvar">
  <?php if ($curso): ?>
  <input type="hidden" name="id" value="<?= $curso['id'] ?>">
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Dados do Curso</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nome do Curso <span class="text-danger">*</span></label>
              <input type="text" name="nome" class="form-control"
                     value="<?= htmlspecialchars($curso['nome'] ?? '') ?>"
                     placeholder="Ex: Técnico em Informática" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Início do Turno <span class="text-danger">*</span></label>
              <input type="time" name="turno_inicio" class="form-control"
                     value="<?= substr($curso['turno_inicio'] ?? '07:00', 0, 5) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Fim do Turno <span class="text-danger">*</span></label>
              <input type="time" name="turno_fim" class="form-control"
                     value="<?= substr($curso['turno_fim'] ?? '12:00', 0, 5) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Duração da Aula (min) <span class="text-danger">*</span></label>
              <input type="number" name="duracao_aula_minutos" class="form-control"
                     min="10" max="240" step="5"
                     value="<?= (int)($curso['duracao_aula_minutos'] ?? 45) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Cor</label>
              <input type="color" name="cor" class="form-control form-control-color w-100"
                     value="<?= htmlspecialchars($curso['cor'] ?? '#3b82f6') ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Status</label>
              <select name="ativo" class="form-select">
                <option value="1" <?= ($curso['ativo'] ?? 1) == 1 ? 'selected':'' ?>>Ativo</option>
                <option value="0" <?= ($curso['ativo'] ?? 1) == 0 ? 'selected':'' ?>>Inativo</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Dias de Funcionamento</label>
              <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($diasSemana as $num => $nome): ?>
                <div class="form-check form-check-inline">
                  <input type="checkbox" name="dias_semana[]" value="<?= $num ?>"
                         class="form-check-input" id="dia_<?= $num ?>"
                         <?= in_array((string)$num, array_map('strval', $diasCurso)) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="dia_<?= $num ?>"><?= $nome ?></label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Intervalos -->
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
          Intervalos (Breaks)
          <button type="button" class="btn btn-sm btn-outline-success" id="btnAddInt">
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>
        <div class="card-body">
          <div class="small text-muted mb-2">
            Deixe "Dia" em branco para aplicar a todos os dias.
          </div>
          <div id="intervalos-container">
            <?php if (empty($intervalos)): ?>
            <div class="intervalo-row mb-2 p-2 border rounded">
              <div class="row g-1 mb-1">
                <div class="col">
                  <select name="int_dia[]" class="form-select form-select-sm">
                    <option value="">Todos os dias</option>
                    <?php foreach ($diasSemana as $n => $nm): ?>
                    <option value="<?= $n ?>"><?= $nm ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-auto">
                  <button type="button" class="btn btn-xs btn-outline-danger remove-int py-1 px-2">✕</button>
                </div>
              </div>
              <div class="row g-1">
                <div class="col">
                  <input type="time" name="int_inicio[]" class="form-control form-control-sm" value="09:30">
                </div>
                <div class="col-auto align-self-center small">até</div>
                <div class="col">
                  <input type="time" name="int_fim[]" class="form-control form-control-sm" value="09:50">
                </div>
              </div>
              <input type="text" name="int_desc[]" class="form-control form-control-sm mt-1" value="Intervalo" placeholder="Descrição">
            </div>
            <?php else: ?>
            <?php foreach ($intervalos as $i): ?>
            <div class="intervalo-row mb-2 p-2 border rounded">
              <div class="row g-1 mb-1">
                <div class="col">
                  <select name="int_dia[]" class="form-select form-select-sm">
                    <option value="" <?= $i['dia_semana'] === null ? 'selected':'' ?>>Todos os dias</option>
                    <?php foreach ($diasSemana as $n => $nm): ?>
                    <option value="<?= $n ?>" <?= (string)$i['dia_semana'] === (string)$n ? 'selected':'' ?>><?= $nm ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-auto">
                  <button type="button" class="btn btn-xs btn-outline-danger remove-int py-1 px-2">✕</button>
                </div>
              </div>
              <div class="row g-1">
                <div class="col">
                  <input type="time" name="int_inicio[]" class="form-control form-control-sm"
                         value="<?= substr($i['hora_inicio'],0,5) ?>">
                </div>
                <div class="col-auto align-self-center small">até</div>
                <div class="col">
                  <input type="time" name="int_fim[]" class="form-control form-control-sm"
                         value="<?= substr($i['hora_fim'],0,5) ?>">
                </div>
              </div>
              <input type="text" name="int_desc[]" class="form-control form-control-sm mt-1"
                     value="<?= htmlspecialchars($i['descricao'] ?? 'Intervalo') ?>" placeholder="Descrição">
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
      <a href="<?= $base ?>/cursos" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </div>
</form>

<script>
const diasOptions2 = <?= json_encode($diasSemana) ?>;

function novaLinhaIntervalo() {
  let opts = '<option value="">Todos os dias</option>';
  for (const [n, nm] of Object.entries(diasOptions2)) {
    opts += `<option value="${n}">${nm}</option>`;
  }
  return `<div class="intervalo-row mb-2 p-2 border rounded">
    <div class="row g-1 mb-1">
      <div class="col"><select name="int_dia[]" class="form-select form-select-sm">${opts}</select></div>
      <div class="col-auto"><button type="button" class="btn btn-xs btn-outline-danger remove-int py-1 px-2">✕</button></div>
    </div>
    <div class="row g-1">
      <div class="col"><input type="time" name="int_inicio[]" class="form-control form-control-sm" value="09:30"></div>
      <div class="col-auto align-self-center small">até</div>
      <div class="col"><input type="time" name="int_fim[]" class="form-control form-control-sm" value="09:50"></div>
    </div>
    <input type="text" name="int_desc[]" class="form-control form-control-sm mt-1" value="Intervalo" placeholder="Descrição">
  </div>`;
}

document.getElementById('btnAddInt').addEventListener('click', function() {
  document.getElementById('intervalos-container').insertAdjacentHTML('beforeend', novaLinhaIntervalo());
});

document.getElementById('intervalos-container').addEventListener('click', function(e) {
  if (e.target.closest('.remove-int')) {
    e.target.closest('.intervalo-row').remove();
  }
});
</script>
