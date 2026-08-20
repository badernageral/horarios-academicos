<?php $pageTitle = 'Configurações'; ?>

<div class="d-flex align-items-center mb-3">
  <h5 class="mb-0 fw-semibold">Configurações</h5>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="<?= $base ?>/configuracoes/salvar">
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">Turnos</div>
    <div class="card-body">
      <p class="small text-muted">
        Estas faixas definem os três turnos da grade de disponibilidade do professor
        e são usadas pelo gerador para decidir se uma aula cabe num turno liberado.
        Os turnos são fixos — o que se ajusta aqui são os horários.
        O ideal é que sejam <strong>contíguas</strong>: o fim de um turno igual ao
        início do próximo.
      </p>

      <?php if (!empty($avisos)): ?>
      <div class="alert alert-warning py-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?php foreach ($avisos as $a): ?>
          <div class="small"><?= htmlspecialchars($a) ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th style="width:34%">Turno</th>
              <th style="width:22%">Início</th>
              <th style="width:22%">Fim</th>
              <th>Duração</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($turnos as $chave => $t): ?>
            <?php
              $ini = \App\Services\TimeHelper::toMinutes($t['inicio']);
              $fim = \App\Services\TimeHelper::toMinutes($t['fim']);
            ?>
            <tr>
              <td>
                <span class="fw-semibold"><?= htmlspecialchars($t['nome']) ?></span>
              </td>
              <td>
                <input type="time" class="form-control form-control-sm"
                       name="turno[<?= htmlspecialchars($chave) ?>][inicio]"
                       value="<?= htmlspecialchars($t['inicio']) ?>" required>
              </td>
              <td>
                <input type="time" class="form-control form-control-sm"
                       name="turno[<?= htmlspecialchars($chave) ?>][fim]"
                       value="<?= htmlspecialchars($t['fim']) ?>" required>
              </td>
              <td class="text-muted small">
                <?= \App\Services\TimeHelper::formatDuration(max(0, $fim - $ini)) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="alert alert-light border mt-3 mb-0 small">
        <i class="bi bi-info-circle me-1"></i>
        Mudar estas faixas <strong>não altera</strong> grades já geradas (os horários
        gravados têm hora própria), mas muda o significado do que cada professor
        marcou: um professor com “Matutino” liberado passa a valer para a faixa nova.
        Vale regerar o horário depois de alterar.
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">
    <i class="bi bi-check-lg me-1"></i>Salvar
  </button>
</form>
