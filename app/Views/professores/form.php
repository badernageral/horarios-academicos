<?php
$pageTitle = $professor ? 'Editar Professor' : 'Novo Professor';
$diasSemana  = $config['dias_semana'] ?? [];
$turnos      = $turnos ?? [];   // vem do controller (tabela `turnos`)
$dispEstados = $config['disp_estados'] ?? [];
// [dia][turno] => 0 não pode | 1 pode | 2 só se precisar
$gradeDisp   = $gradeDisp ?? [];

?>

<div class="d-flex align-items-center mb-3">
  <a href="<?= $base ?>/professores" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><?= $pageTitle ?></h5>
</div>

<form method="POST" action="<?= $base ?>/professores/salvar">
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
            <div class="col-md-5">
              <label class="form-label">Nome <span class="text-danger">*</span></label>
              <input type="text" name="nome" class="form-control"
                     value="<?= htmlspecialchars($professor['nome'] ?? '') ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Cor Primária</label>
              <input type="color" id="cor" name="cor" class="form-control form-control-color w-100"
                     value="<?= htmlspecialchars($professor['cor'] ?? '#3b82f6') ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Cor Secundária</label>
              <input type="color" id="cor_secundaria" name="cor_secundaria" class="form-control form-control-color w-100"
                     value="<?= htmlspecialchars($professor['cor_secundaria'] ?? '#f97316') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="ativo" class="form-select">
                <option value="1" <?= ($professor['ativo'] ?? 1) == 1 ? 'selected':'' ?>>Ativo</option>
                <option value="0" <?= ($professor['ativo'] ?? 1) == 0 ? 'selected':'' ?>>Inativo</option>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">NDA (Área) <span class="text-danger">*</span></label>
              <select name="nda_id" class="form-select" required>
                <option value="">Selecione uma opção</option>
                <?php foreach ($ndas ?? [] as $n): ?>
                <option value="<?= $n['id'] ?>"
                  <?= ($professor['nda_id'] ?? '') == $n['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($n['nome']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <?php // Campo `usuario_moodle`: a matrícula do professor É o login do Moodle. ?>
              <label class="form-label">Matrícula</label>
              <input type="text" name="usuario_moodle" class="form-control"
                     value="<?= htmlspecialchars($professor['usuario_moodle'] ?? '') ?>"
                     placeholder="ex: jose.alves">
              <div class="form-text">Usada como login na exportação de inscrições do Moodle</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Disponibilidade semanal: 3 turnos x 5 dias -->
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">
          Disponibilidade Semanal
        </div>
        <div class="card-body">
          <div class="mb-3 small text-muted">
            Clique em cada turno para alternar entre os três estados:
            <span class="disp-legenda disp-sim"><i class="bi bi-check-lg"></i></span> pode dar aula &nbsp;
            <span class="disp-legenda disp-nao"><i class="bi bi-x-lg"></i></span> não pode &nbsp;
            <span class="disp-legenda disp-talvez"><i class="bi bi-question-lg"></i></span>
            só se não houver turno verde disponível.
          </div>

          <div class="table-responsive">
            <table class="table table-bordered disp-grade align-middle text-center mb-0">
              <thead>
                <tr>
                  <th style="width:15%"></th>
<?php foreach ($diasSemana as $numDia => $nomeDia): ?>
                  <th class="fw-semibold"><?= htmlspecialchars($nomeDia) ?></th>
<?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
<?php foreach ($turnos as $chaveTurno => $turno): ?>
                <tr>
                  <th class="text-start fw-semibold">
                    <?= htmlspecialchars($turno['nome']) ?>
                    <div class="small text-muted fw-normal">
                      <?= htmlspecialchars($turno['inicio']) ?>–<?= htmlspecialchars($turno['fim']) ?>
                    </div>
                  </th>
<?php foreach ($diasSemana as $numDia => $nomeDia): ?>
<?php   $estado = (int)($gradeDisp[$numDia][$chaveTurno] ?? 0); ?>
<?php   $info   = $dispEstados[$estado]; ?>
                  <td class="p-1">
                    <button type="button"
                            class="disp-cel <?= $info['classe'] ?>"
                            data-estado="<?= $estado ?>"
                            title="<?= htmlspecialchars($nomeDia) ?> / <?= htmlspecialchars($turno['nome']) ?>: <?= htmlspecialchars($info['rotulo']) ?>"
                            aria-label="<?= htmlspecialchars($nomeDia) ?> <?= htmlspecialchars($turno['nome']) ?>">
                      <i class="bi <?= $info['icone'] ?>"></i>
                    </button>
                    <input type="hidden" name="disp[<?= $numDia ?>][<?= htmlspecialchars($chaveTurno) ?>]"
                           value="<?= $estado ?>">
                  </td>
<?php endforeach; ?>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Salvar
      </button>
      <a href="<?= $base ?>/professores" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </div>
</form>

<script>
// Auto-suggest complementary secondary color when primary changes
(function() {
  const corInput    = document.getElementById('cor');
  const corSecInput = document.getElementById('cor_secundaria');
  let userEditedSec = false;

  corSecInput.addEventListener('input', () => { userEditedSec = true; });

  corInput.addEventListener('input', function() {
    if (userEditedSec) return;
    const hex = parseInt(this.value.slice(1), 16);
    corSecInput.value = '#' + (0xFFFFFF ^ hex).toString(16).padStart(6, '0');
  });
})();

// Grade de disponibilidade: cada retângulo cicla verde -> vermelho -> interrogação.
(function() {
  const ESTADOS = <?= json_encode($dispEstados) ?>;
  const CICLO   = [1, 0, 2];   // pode -> não pode -> só se precisar

  document.querySelectorAll('.disp-cel').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const atual = parseInt(btn.dataset.estado, 10);
      const proximo = CICLO[(CICLO.indexOf(atual) + 1) % CICLO.length];
      const info = ESTADOS[proximo];

      btn.dataset.estado = proximo;
      btn.className = 'disp-cel ' + info.classe;
      btn.querySelector('i').className = 'bi ' + info.icone;

      // O título carrega "Dia / Turno: rótulo" — troca só o rótulo final.
      btn.title = btn.title.replace(/: .*$/, ': ' + info.rotulo);

      const campo = btn.parentElement.querySelector('input[type=hidden]');
      if (campo) campo.value = proximo;
    });
  });
})();
</script>
