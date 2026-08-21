<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Horários — Consulta pública</title>
  <link href="<?= $base ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="<?= $base ?>/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; }
    .grade-table { border-collapse: collapse; table-layout: fixed; }
    .grade-table th, .grade-table td { border: 1px solid #e2e8f0; }
    .grade-table th {
      background: #f1f5f9; font-size: 12px; font-weight: 600;
      text-align: center; padding: 7px 6px; white-space: nowrap;
    }
    .col-hora { width: 92px; font-size: 11px; color: #64748b; text-align: left; }
    td.col-hora { background: #f8fafc; padding: 3px 8px; white-space: nowrap; }
    tr.slot-row { height: 62px; }
    tr.intervalo-row { height: 20px; }
    .intervalo-row td { background: #f8fafc; color: #94a3b8; font-size: 10px; text-align: center; }
    .celula { position: relative; padding: 3px; min-width: 130px; background: #fff; vertical-align: top; }
    .bloco {
      position: absolute; top: 4px; right: 4px; bottom: 4px; left: 4px;
      border-radius: 5px; padding: 4px 7px 20px; overflow: hidden;
      line-height: 1.3; box-sizing: border-box;
    }
    .bloco .titulo { font-weight: 700; font-size: 12px; }
    .bloco .hora   { font-weight: 700; font-size: 12px; }
    .bloco .nota   { font-style: italic; }
    .bloco .nota:not(:empty)::before { content: ' · '; opacity: .6; }
    .faixa {
      position: absolute; bottom: 0; left: 0; right: 0;
      font-size: 10px; font-weight: 600; padding: 2px 6px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    @media print { .no-print { display: none !important; } body { background: #fff; } }
  </style>
</head>
<body>

<nav class="navbar bg-white border-bottom mb-3 no-print">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 d-flex align-items-center gap-2">
      <i class="bi bi-calendar2-week-fill text-primary"></i>
      <span class="fw-bold">Horários Acadêmicos</span>
    </span>
    <a href="<?= $base ?>/login" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
    </a>
  </div>
</nav>

<div class="container-fluid pb-5">

  <?php if (!$geracao): ?>
    <div class="alert alert-info">
      <i class="bi bi-info-circle me-2"></i>
      Não há horário publicado para o
      <strong><?= $semestreAtual ?>º semestre de <?= $anoAtual ?></strong>.
    </div>
  <?php else: ?>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <h5 class="mb-0 fw-semibold">
        Horário — <?= (int)$geracao['semestre'] ?>º Semestre / <?= (int)$geracao['ano'] ?>
      </h5>
    </div>

    <?php // Filtros: o aluno acha a turma dele; o professor, as aulas dele. ?>
    <form method="GET" class="row g-2 align-items-end mb-3 no-print">
      <div class="col-sm-5 col-md-4">
        <label class="form-label small mb-1" for="turma_id">Turma</label>
        <select name="turma_id" id="turma_id" class="form-select form-select-sm">
          <option value="0">Todas as turmas</option>
          <?php foreach ($turmas as $tid => $rotulo): ?>
          <option value="<?= $tid ?>" <?= $turmaFiltro === $tid ? 'selected' : '' ?>>
            <?= htmlspecialchars($rotulo) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-5 col-md-4">
        <label class="form-label small mb-1" for="professor_id">Professor</label>
        <select name="professor_id" id="professor_id" class="form-select form-select-sm">
          <option value="0">Todos os professores</option>
          <?php foreach ($professores as $pid => $nome): ?>
          <option value="<?= $pid ?>" <?= $profFiltro === $pid ? 'selected' : '' ?>>
            <?= htmlspecialchars($nome) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
        <a href="<?= $base ?>/publico" class="btn btn-sm btn-outline-secondary">Limpar</a>
        <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()">
          <i class="bi bi-printer me-1"></i>Imprimir
        </button>
      </div>
    </form>

    <?php if (empty($grade)): ?>
      <div class="alert alert-warning">Nenhuma aula encontrada para esse filtro.</div>
    <?php endif; ?>

    <?php foreach ($grade as $turmaId => $row): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-dark text-white fw-semibold py-2">
        <?= htmlspecialchars($row['curso_nome']) ?> — <?= htmlspecialchars($row['turma_nome']) ?>
        <?php if (!empty($anotacoes[$turmaId])): ?>
        <span class="fw-normal fst-italic opacity-75">— <?= htmlspecialchars($anotacoes[$turmaId]) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="grade-table w-100">
          <thead>
            <tr>
              <th class="col-hora">Hora</th>
              <?php foreach ($dias as $dNome): ?><th><?= htmlspecialchars($dNome) ?></th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($row['slots'] as $slotIdx => $slot): ?>
            <?php if ($slot['type'] === 'intervalo'): ?>
            <tr class="intervalo-row">
              <td colspan="<?= count($dias) + 1 ?>">
                Intervalo — <?= \App\Services\TimeHelper::fromMinutes($slot['min']) ?>
                às <?= \App\Services\TimeHelper::fromMinutes($slot['fim']) ?>
              </td>
            </tr>
            <?php continue; endif; ?>
            <tr class="slot-row">
              <td class="col-hora">
                <?= \App\Services\TimeHelper::fromMinutes($slot['min']) ?>–<?= \App\Services\TimeHelper::fromMinutes($slot['fim']) ?>
              </td>
              <?php foreach ($dias as $dNum => $dNome): ?>
                <?php $h = $row['grid'][$dNum][$slotIdx] ?? null; ?>
                <?php if ($h === null): ?>
                <td class="celula"></td>
                <?php else: ?>
                <?php
                  $cor    = $h['professor_cor'] ?: '#94a3b8';
                  $corSec = $h['professor_cor_secundaria'] ?: $cor;
                  $txt    = \App\Services\ColorHelper::textoSobre($cor, 0.35);
                  $txtSec = \App\Services\ColorHelper::textoSobre($corSec);
                ?>
                <td class="celula">
                  <div class="bloco" style="background:<?= htmlspecialchars($cor) ?>59">
                    <div class="titulo" style="color:<?= $txt ?>">
                      <?= htmlspecialchars($h['disciplina_sigla'] ?: $h['disciplina_nome']) ?>
                    </div>
                    <div class="hora" style="color:<?= $txt ?>">
                      <?= \App\Services\TimeHelper::fromMinutes($h['slot_ini']) ?>–<?= \App\Services\TimeHelper::fromMinutes($h['slot_fim']) ?><span
                        class="nota"><?= !empty($h['observacao']) ? htmlspecialchars($h['observacao']) : '' ?></span>
                    </div>
                    <?php if ($h['professor_nome']): ?>
                    <div class="faixa" style="background:<?= htmlspecialchars($corSec) ?>;color:<?= $txtSec ?>">
                      <?= htmlspecialchars($h['professor_nome']) ?><?= $h['sala_nome'] ? ' · ' . htmlspecialchars($h['sala_nome']) : '' ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </td>
                <?php endif; ?>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>
</body>
</html>
