<?php
$pageTitle    = 'Atribuição de Professores e Salas';
$semestreLabel = $semestre['semestre'] . 'º Semestre / ' . $semestre['ano'];

// Turmas únicas presentes nesta lista de disciplinas, para o select do modal
// "Definir sala por turma" — evita uma consulta nova, os dados já vieram.
$turmasUnicas = [];
foreach ($disciplinas as $d) {
    $turmasUnicas[(int)$d['turma_id']] ??= $d['curso_nome'] . ' – ' . $d['turma_nome'];
}
asort($turmasUnicas, SORT_NATURAL | SORT_FLAG_CASE);
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <a href="<?= $base ?>/horarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h5 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-primary"></i>Atribuição de Professores e Salas</h5>
    <small class="text-muted"><?= $semestreLabel ?></small>
  </div>
  <a href="<?= $base ?>/horarios/<?= $semestreId ?>/atribuir/importar" class="btn btn-sm btn-outline-primary ms-auto">
    <i class="bi bi-cloud-upload me-1"></i>Importar em Massa
  </a>
  <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalSalaTurma">
    <i class="bi bi-door-open me-1"></i>Definir sala por turma
  </button>
</div>

<div id="avisoSalaTurma"></div>

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
  <form method="POST" action="<?= $base ?>/horarios/<?= $semestreId ?>/atribuir">
    <div class="px-3 pt-3 pb-2 border-bottom d-flex align-items-center flex-wrap gap-2">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="filtroNdaProfessor" checked>
        <label class="form-check-label small" for="filtroNdaProfessor">
          Mostrar apenas professores do mesmo NDA da disciplina
        </label>
      </div>
      <div class="ms-auto d-flex gap-2">
        <?php if ($semAtribuir > 0): ?>
        <span class="badge bg-danger text-white fs-6"><?= $semAtribuir ?> sem professor</span>
        <?php else: ?>
        <span class="badge bg-success fs-6"><i class="bi bi-check-lg me-1"></i>Professores OK</span>
        <?php endif; ?>
        <?php if ($semSala > 0): ?>
        <span class="badge bg-danger text-white fs-6"><?= $semSala ?> sem sala</span>
        <?php else: ?>
        <span class="badge bg-success fs-6"><i class="bi bi-check-lg me-1"></i>Salas OK</span>
        <?php endif; ?>
      </div>
    </div>
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
        <?php foreach ($grupos as $ndaId => $g): ?>
        <?php // Cabeçalho do NDA: mesma tabela, uma seção por núcleo. ?>
        <tr class="table-secondary">
          <td colspan="6" class="fw-semibold py-1">
            <i class="bi bi-diagram-3 me-2"></i><?= htmlspecialchars($g['nome']) ?>
            <span class="badge text-bg-light border ms-1"><?= count($g['disciplinas']) ?></span>
          </td>
        </tr>
        <?php foreach ($g['disciplinas'] as $d):
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
                <select name="atribuicao[<?= $d['id'] ?>][<?= $slot ?>]" class="form-select form-select-sm select-professor"
                        data-disc-nda="<?= (int)($d['nda_id'] ?? 0) ?>">
                  <option value="">— Sem professor —</option>
                  <?php foreach ($professores as $p): ?>
                  <option value="<?= $p['id'] ?>" data-prof-nda="<?= (int)($p['nda_id'] ?? 0) ?>"
                          <?= $profAtrib == $p['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nome']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endfor; ?>
            </td>
            <td>
              <select name="sala[<?= $d['id'] ?>]" class="form-select form-select-sm select-sala"
                      data-turma-id="<?= (int)$d['turma_id'] ?>">
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
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Salvar Atribuições
      </button>
      <a href="<?= $base ?>/horarios" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
</div>

<!-- Modal: definir sala de N turmas de uma vez -->
<div class="modal fade" id="modalSalaTurma" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-door-open me-2"></i>Definir sala por turma</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">
          Escolha a turma e a sala em cada linha — todas as disciplinas dessa turma nesta lista recebem a mesma sala.
          Isso só preenche os selects abaixo; nada é gravado até você clicar em "Salvar Atribuições".
        </p>
        <div id="linhasSalaTurma">
          <div class="row g-2 mb-2 linha-sala-turma">
            <div class="col-6">
              <select class="form-select form-select-sm select-turma-modal">
                <option value="">Selecione a turma...</option>
                <?php foreach ($turmasUnicas as $tid => $label): ?>
                <option value="<?= $tid ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-5">
              <select class="form-select form-select-sm select-sala-modal">
                <option value="">Selecione a sala...</option>
                <?php foreach ($salas as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-1 d-flex align-items-center justify-content-center">
              <button type="button" class="btn btn-sm btn-outline-danger btn-remover-linha" title="Remover linha">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>
        </div>
        <button type="button" id="btnAdicionarLinhaSalaTurma" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-plus-lg me-1"></i>Adicionar linha
        </button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnAplicarSalaTurma" class="btn btn-primary">
          <i class="bi bi-check-lg me-1"></i>Aplicar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const container    = document.getElementById('linhasSalaTurma');
  const btnAdicionar  = document.getElementById('btnAdicionarLinhaSalaTurma');
  const btnAplicar    = document.getElementById('btnAplicarSalaTurma');
  const modalEl       = document.getElementById('modalSalaTurma');
  const aviso         = document.getElementById('avisoSalaTurma');

  function linhaVazia() {
    const linha = container.querySelector('.linha-sala-turma').cloneNode(true);
    linha.querySelectorAll('select').forEach(function (s) { s.value = ''; });
    return linha;
  }

  btnAdicionar.addEventListener('click', function () {
    container.appendChild(linhaVazia());
  });

  // Delegação: funciona também para linhas adicionadas depois.
  container.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-remover-linha');
    if (!btn) return;
    if (container.querySelectorAll('.linha-sala-turma').length > 1) {
      btn.closest('.linha-sala-turma').remove();
    }
  });

  btnAplicar.addEventListener('click', function () {
    let disciplinasPreenchidas = 0;
    let turmasAplicadas = 0;

    container.querySelectorAll('.linha-sala-turma').forEach(function (linha) {
      const turmaId = linha.querySelector('.select-turma-modal').value;
      const salaId  = linha.querySelector('.select-sala-modal').value;
      if (!turmaId || !salaId) return;

      const alvos = document.querySelectorAll('.select-sala[data-turma-id="' + turmaId + '"]');
      alvos.forEach(function (sel) { sel.value = salaId; });
      if (alvos.length > 0) {
        disciplinasPreenchidas += alvos.length;
        turmasAplicadas++;
      }
    });

    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    if (disciplinasPreenchidas > 0) {
      aviso.innerHTML =
        '<div class="alert alert-success alert-dismissible fade show mt-2 mb-0">' +
        'Sala preenchida em ' + disciplinasPreenchidas + ' disciplina(s) de ' + turmasAplicadas + ' turma(s). ' +
        'Clique em <strong>Salvar Atribuições</strong> para confirmar.' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';

      // Volta o modal para uma única linha vazia, pronto para o próximo lote.
      container.querySelectorAll('.linha-sala-turma').forEach(function (linha, i) {
        if (i === 0) { linha.querySelectorAll('select').forEach(function (s) { s.value = ''; }); }
        else { linha.remove(); }
      });
    }
  });
})();
</script>

<script>
(function () {
  const checkbox = document.getElementById('filtroNdaProfessor');
  const selects  = document.querySelectorAll('.select-professor');

  function aplicarFiltro() {
    const ativo = checkbox.checked;
    selects.forEach(function (sel) {
      const discNda = parseInt(sel.dataset.discNda, 10) || 0;
      Array.from(sel.options).forEach(function (opt) {
        if (!opt.value) return; // "— Sem professor —" sempre visível
        const profNda = parseInt(opt.dataset.profNda, 10) || 0;
        // "Qualquer NDA" (discNda = 0) não tem o que filtrar: mostra todos.
        // A opção já selecionada nunca é escondida, para não sumir uma
        // atribuição antiga feita antes deste filtro existir.
        const bloquear = ativo && discNda !== 0 && profNda !== discNda && !opt.selected;
        opt.hidden    = bloquear;
        opt.disabled  = bloquear;
      });
    });
  }

  checkbox.addEventListener('change', aplicarFiltro);
  selects.forEach(function (sel) { sel.addEventListener('change', aplicarFiltro); });
  aplicarFiltro();
})();
</script>

<?php // Relatório de carga por NDA do PROFESSOR. Vem das atribuições acima, não
      // da grade gerada — serve para conferir a distribuição ANTES de gerar. ?>
<div class="card border-0 shadow-sm mt-4">
  <div class="card-header bg-transparent fw-semibold">
    <i class="bi bi-clock-history me-2"></i>Carga horária por professor
  </div>
  <div class="card-body">

    <?php if (empty($cargaPorNda)): ?>
      <p class="text-muted mb-0">Nenhum professor ativo cadastrado.</p>
    <?php else: ?>

    <?php foreach ($cargaPorNda as $g): ?>
    <div class="mb-4">
      <div class="d-flex align-items-center gap-2 border-bottom pb-1 mb-2">
        <span class="fw-semibold"><i class="bi bi-diagram-3 me-1"></i><?= htmlspecialchars($g['nome']) ?></span>
        <span class="badge text-bg-light border"><?= count($g['professores']) ?> professor(es)</span>
        <?php $semCargaNda = count(array_filter($g['professores'], fn($x) => empty($x['disciplinas']))); ?>
        <?php if ($semCargaNda > 0): ?>
        <span class="badge text-bg-warning"><?= $semCargaNda ?> sem carga</span>
        <?php endif; ?>
        <span class="ms-auto small text-muted">
          Total do NDA:
          <strong><?= $g['aulas_total'] ?></strong> aulas<?php
            if ($g['ead'] > 0): ?> (<?= $g['aulas'] ?> + <?= $g['ead'] ?> EaD)<?php endif; ?> ·
          <strong><?= $g['minutos'] > 0 ? \App\Services\TimeHelper::formatDuration($g['minutos']) : '0h' ?></strong>
        </span>
      </div>

      <?php // Uma linha por professor; as disciplinas ficam na própria linha. ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" style="font-size:13px">
          <thead class="table-light">
            <tr>
              <th style="width:18%">Professor</th>
              <th>Disciplinas</th>
              <th class="text-center" style="width:10%"
                  title="Aulas por semana: presenciais + EaD">Aulas</th>
              <th class="text-center" style="width:14%"
                  title="Tempo ocupado na grade — só as aulas presenciais">Carga relógio</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($g['professores'] as $p): ?>
            <?php $semCarga = empty($p['disciplinas']); ?>
            <tr <?= $semCarga ? 'class="table-warning"' : '' ?>>
              <td class="fw-semibold"><?= htmlspecialchars($p['nome']) ?></td>
              <td>
                <?php if ($semCarga): ?>
                  <span class="text-muted fst-italic">sem disciplinas atribuídas</span>
                <?php else: ?>
                  <?php foreach ($p['disciplinas'] as $i => $d): ?><?= $i ? ' · ' : '' ?><span
                    title="<?= htmlspecialchars($d['turma']) ?> — <?= $d['encontros'] ?> encontro(s) por semana"><?=
                    htmlspecialchars($d['nome']) ?><span class="text-muted"> (<?= htmlspecialchars($d['turma']) ?>)</span><?php
                    if ($d['dividida']): ?><span class="badge text-bg-light border ms-1"
                      title="Disciplina com mais de um professor: os encontros são divididos">dividida</span><?php endif; ?></span><?php endforeach; ?>
                  <?php if ($p['ead'] > 0): ?>
                  <span class="badge text-bg-info ms-1"><?= $p['ead'] ?> EaD</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <?php // Total semanal de aulas (presencial + EaD), com a quebra embaixo. ?>
              <td class="text-center">
                <?= $p['aulas_total'] ?>
                <?php if ($p['ead'] > 0): ?>
                <div class="text-muted" style="font-size:11px"><?= $p['aulas'] ?> + <?= $p['ead'] ?> EaD</div>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($semCarga): ?>
                  <span class="text-muted">0h</span>
                <?php else: ?>
                  <span class="badge bg-primary"><?= \App\Services\TimeHelper::formatDuration($p['minutos']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
  </div>
</div>

<?php // Relatório de salas: blocos lado a lado, cada um com suas disciplinas. ?>
<div class="card border-0 shadow-sm mt-4">
  <div class="card-header bg-transparent fw-semibold">
    <i class="bi bi-door-open me-2"></i>Ocupação das salas
  </div>
  <div class="card-body">
    <?php if (empty($ocupacaoSalas)): ?>
      <p class="text-muted mb-0">Nenhuma sala ativa cadastrada.</p>
    <?php else: ?>
    <div class="row g-3">
      <?php foreach ($ocupacaoSalas as $sala): ?>
      <?php $vazia = empty($sala['disciplinas']); ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="border rounded h-100">
          <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2
                      <?= $sala['id'] === 0 ? 'bg-warning-subtle' : 'bg-light' ?>">
            <span class="fw-semibold">
              <i class="bi bi-<?= $sala['id'] === 0 ? 'exclamation-triangle' : 'door-open' ?> me-1"></i>
              <?= htmlspecialchars($sala['nome']) ?>
            </span>
            <span class="ms-auto small text-muted">
              <?php if ($vazia): ?>
                sem disciplinas
              <?php else: ?>
                <?= count($sala['disciplinas']) ?> disc. · <?= $sala['aulas'] ?> aulas ·
                <?= \App\Services\TimeHelper::formatDuration($sala['minutos']) ?>
              <?php endif; ?>
            </span>
          </div>
          <div class="px-3 py-2">
            <?php if ($vazia): ?>
              <span class="small text-muted fst-italic">Nenhuma disciplina atribuída a esta sala.</span>
            <?php else: ?>
            <ul class="list-unstyled mb-0 small">
              <?php foreach ($sala['disciplinas'] as $d): ?>
              <li class="d-flex gap-2 py-1 border-bottom">
                <span class="flex-grow-1">
                  <?= htmlspecialchars($d['nome']) ?>
                  <span class="text-muted d-block" style="font-size:12px"><?= htmlspecialchars($d['turma']) ?></span>
                </span>
                <span class="text-nowrap text-muted"><?= $d['aulas'] ?> aulas</span>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
