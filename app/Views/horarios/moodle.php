<?php
$pageTitle = 'Exportar para o Moodle';
$colisoes  = $diag['colisoes'] ?? [];
$semUser   = $diag['sem_usuario'] ?? [];
$map       = $diag['map'] ?? [];
$rows      = $diag['rows'] ?? [];
?>

<div class="d-flex align-items-center mb-3">
  <a href="<?= $base ?>/horarios" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold">
    <i class="bi bi-mortarboard me-2 text-primary"></i>Exportar para o Moodle
    <span class="text-muted fw-normal">— <?= $semestre['semestre'] ?>º Semestre / <?= $semestre['ano'] ?></span>
  </h5>
</div>

<div class="alert alert-info small">
  <i class="bi bi-info-circle me-1"></i>
  O <strong>shortname</strong> de cada curso segue o padrão
  <code><?= $semestre['ano'] ?><?= $semestre['semestre'] ?>{curso}{período}{disciplina}</code>
  (ex.: <code><?= $semestre['ano'] ?><?= $semestre['semestre'] ?>A4LP</code>) e é a chave que liga os dois arquivos.
  <?php if ((int)$semestre['semestre'] === 2): ?>
  <div class="mt-2"><i class="bi bi-calendar-range me-1"></i>
    Disciplinas <strong>anuais</strong> não entram nesta exportação (já foram criadas no 1º semestre).
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($colisoes)): ?>
<div class="alert alert-warning">
  <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Shortnames repetidos (resolvidos com sufixo numérico)</div>
  <ul class="small mb-0">
    <?php foreach ($colisoes as $shortname => $discs): ?>
    <li><code><?= htmlspecialchars($shortname) ?></code>: <?= htmlspecialchars(implode(' / ', $discs)) ?></li>
    <?php endforeach; ?>
  </ul>
  <div class="small mt-1">As iniciais coincidiram. Para shortnames mais limpos, ajuste o nome dessas disciplinas.</div>
</div>
<?php endif; ?>

<?php if (!empty($semUser)): ?>
<div class="alert alert-warning">
  <div class="fw-semibold mb-1"><i class="bi bi-person-exclamation me-1"></i>Professores sem usuário Moodle (não entram no CSV de professores)</div>
  <div class="small"><?= htmlspecialchars(implode(', ', $semUser)) ?></div>
  <div class="small mt-1">Preencha o campo <em>Usuário Moodle</em> no cadastro de cada um para incluí-los.</div>
</div>
<?php endif; ?>

<div class="row g-3">
  <!-- CSV de professores -->
  <div class="col-md-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-transparent fw-semibold"><i class="bi bi-person-badge me-1"></i>CSV de Professores</div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted">
          Formato <em>Upload users</em>: <code>username,course1,role1,…</code> com papel
          <code>editingteacher</code>. Cada professor é inscrito nos cursos que leciona neste semestre.
        </p>
        <a href="<?= $base ?>/horarios/<?= $semestre['id'] ?>/moodle/professores"
           class="btn btn-success mt-auto">
          <i class="bi bi-download me-1"></i>Baixar CSV de professores
        </a>
      </div>
    </div>
  </div>

  <!-- CSV de disciplinas -->
  <div class="col-md-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-transparent fw-semibold"><i class="bi bi-journal-bookmark me-1"></i>CSV de Disciplinas (cursos)</div>
      <div class="card-body">
        <form method="POST" action="<?= $base ?>/horarios/<?= $semestre['id'] ?>/moodle/disciplinas">
          <p class="small text-muted">
            Formato <em>Upload courses</em>. Informe o <strong>ID da categoria</strong> de cada turma
            (muda a cada semestre) e, opcionalmente, as datas do período.
          </p>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small">Início (startdate)</label>
              <input type="date" name="startdate" class="form-control form-control-sm">
            </div>
            <div class="col-6">
              <label class="form-label small">Fim (enddate)</label>
              <input type="date" name="enddate" class="form-control form-control-sm">
            </div>
          </div>

          <?php if (empty($turmas)): ?>
          <div class="alert alert-secondary small mb-3">Nenhuma disciplina ofertada neste semestre.</div>
          <?php else: ?>
          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th class="small">Turma</th>
                  <th class="small text-center">Disciplinas</th>
                  <th class="small" style="width:130px">ID categoria</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($turmas as $t): ?>
                <tr>
                  <td class="small"><?= htmlspecialchars($t['curso_nome'] . ' – ' . $t['serie_periodo']) ?></td>
                  <td class="small text-center"><?= (int)$t['qtd'] ?></td>
                  <td>
                    <input type="text" name="categoria[<?= (int)$t['turma_id'] ?>]"
                           class="form-control form-control-sm" inputmode="numeric" placeholder="ex: 11">
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-download me-1"></i>Baixar CSV de disciplinas
          </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Pré-visualização dos shortnames -->
<?php if (!empty($rows)): ?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center"
       data-bs-toggle="collapse" data-bs-target="#previewShort" style="cursor:pointer">
    <span><i class="bi bi-list-columns me-1"></i>Pré-visualização dos shortnames (<?= count($rows) ?>)</span>
    <i class="bi bi-chevron-down"></i>
  </div>
  <div class="collapse" id="previewShort">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr><th class="small">shortname</th><th class="small">Disciplina</th><th class="small">Turma</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><code><?= htmlspecialchars($map[(int)$r['disciplina_id']] ?? '—') ?></code></td>
            <td class="small"><?= htmlspecialchars($r['disciplina_nome']) ?></td>
            <td class="small"><?= htmlspecialchars($r['curso_nome'] . ' – ' . $r['serie_periodo']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
