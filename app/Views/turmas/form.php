<?php $pageTitle = $turma ? 'Editar Turma' : 'Nova Turma'; ?>

<div class="d-flex align-items-center mb-3">
  <a href="<?= $base ?>/turmas" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold"><?= $pageTitle ?></h5>
</div>

<div class="card border-0 shadow-sm" style="max-width:500px">
  <div class="card-body">
    <form method="POST" action="<?= $base ?>/turmas/salvar">
      <?php if ($turma): ?>
      <input type="hidden" name="id" value="<?= $turma['id'] ?>">
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Curso <span class="text-danger">*</span></label>
        <select name="curso_id" class="form-select" required>
          <option value="">Selecione...</option>
          <?php foreach ($cursos as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($turma['curso_id'] ?? '') == $c['id'] ? 'selected':'' ?>>
            <?= htmlspecialchars($c['nome']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Série/Período <span class="text-danger">*</span></label>
          <input type="text" name="serie_periodo" class="form-control"
                 value="<?= htmlspecialchars($turma['serie_periodo'] ?? '') ?>"
                 placeholder="Ex: 1º Ano" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="ativo" class="form-select">
            <option value="1" <?= ($turma['ativo'] ?? 1) == 1 ? 'selected':'' ?>>Ativa</option>
            <option value="0" <?= ($turma['ativo'] ?? 1) == 0 ? 'selected':'' ?>>Inativa</option>
          </select>
        </div>
      </div>

      <?php
        // Lacunas da turma: mesma grade 3 turnos x 5 dias do professor, mas só
        // com dois estados. Verde = pode ter aula; vermelho = bloqueado.
        $diasSemana  = $config['dias_semana'] ?? [1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta'];
        $dispEstados = $config['disp_estados'] ?? [];
        $turnos      = $turnos ?? [];
        $gradeDisp   = $gradeDisp ?? [];
      ?>
      <hr class="my-4">
      <div class="fw-semibold mb-2">Turnos com aula</div>
      <div class="mb-3 small text-muted">
        Clique para bloquear os turnos em que esta turma NÃO tem aula
        (<span class="disp-legenda disp-sim"><i class="bi bi-check-lg"></i></span> pode &nbsp;
        <span class="disp-legenda disp-nao"><i class="bi bi-x-lg"></i></span> não pode).
        Disciplina que não couber nos turnos liberados vai para o limbo.
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
<?php   $info   = $dispEstados[$estado] ?? $dispEstados[0]; ?>
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

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i>Salvar
        </button>
        <a href="<?= $base ?>/turmas" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
// Lacunas da turma: só dois estados (pode / não pode), sem a interrogação
// que existe na disponibilidade do professor.
(function() {
  const ESTADOS = <?= json_encode($dispEstados) ?>;
  const CICLO   = [1, 0];

  document.querySelectorAll('.disp-cel').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const atual   = parseInt(btn.dataset.estado, 10);
      const proximo = CICLO[(CICLO.indexOf(atual) + 1) % CICLO.length];
      const info    = ESTADOS[proximo];

      btn.dataset.estado = proximo;
      btn.className = 'disp-cel ' + info.classe;
      btn.querySelector('i').className = 'bi ' + info.icone;
      btn.title = btn.title.replace(/: .*$/, ': ' + info.rotulo);

      const campo = btn.parentElement.querySelector('input[type=hidden]');
      if (campo) campo.value = proximo;
    });
  });
})();
</script>
