<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Horários por Professor – <?= htmlspecialchars($geracao['descricao'] ?? '') ?></title>
  <style>
    * { box-sizing: border-box; print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
    body {
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      margin: 0; padding: 16px; background: #fff; color: #1e293b;
    }
    .prof-bloco { margin-bottom: 28px; }
    .prof-bloco + .prof-bloco { break-before: page; page-break-before: always; }
    .prof-header {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 8px; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0;
    }
    .prof-swatch { width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0; }
    .prof-nome { font-size: 16px; font-weight: 700; }
    .prof-meta { font-size: 11px; color: #64748b; margin-left: auto; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 1px solid #cbd5e1; vertical-align: top; padding: 4px; }
    th { background: #f1f5f9; font-size: 12px; padding: 6px 4px; }
    .aula {
      border-radius: 4px; padding: 4px 6px; margin-bottom: 4px;
      font-size: 10.5px; line-height: 1.35; position: relative; overflow: hidden;
    }
    .aula strong { font-size: 11px; }
    .aula small { color: #475569; }
    .aula .faixa {
      margin: 3px -6px -4px; padding: 2px 6px;
      color: #fff; font-weight: 600; font-size: 9.5px;
    }
    .sem-aula { color: #cbd5e1; font-size: 10px; font-style: italic; text-align: center; padding: 8px 0; }
    .no-print {
      margin-bottom: 14px; display: flex; gap: 8px; align-items: center;
    }
    .no-print button, .no-print a {
      font-size: 13px; padding: 6px 14px; border-radius: 6px; text-decoration: none;
      border: 1px solid #94a3b8; background: #fff; color: #1e293b; cursor: pointer;
    }
    @media print { .no-print { display: none !important; } }
    @page { size: landscape; margin: 8mm; }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">🖨️ Imprimir</button>
  <a href="<?= $base ?>/horarios/geracao/<?= $geracaoId ?>/grade">← Voltar à grade</a>
  <span style="font-size:12px;color:#64748b"><?= count($porProfessor) ?> professor(es) — um por página</span>
</div>

<?php foreach ($porProfessor as $nome => $p): ?>
<div class="prof-bloco">
  <div class="prof-header">
    <span class="prof-swatch" style="background:linear-gradient(to right, <?= $p['cor'] ?> 50%, <?= $p['cor_sec'] ?> 50%)"></span>
    <span class="prof-nome"><?= htmlspecialchars($nome) ?></span>
    <span class="prof-meta">
      <?= htmlspecialchars($geracao['descricao'] ?? '') ?>
    </span>
  </div>
  <table>
    <tr>
      <?php foreach ($dias as $dNome): ?>
      <th><?= $dNome ?></th>
      <?php endforeach; ?>
    </tr>
    <tr>
      <?php foreach ($dias as $dNum => $dNome): ?>
      <td>
        <?php if (empty($p['dias'][$dNum])): ?>
        <div class="sem-aula">—</div>
        <?php else: foreach ($p['dias'][$dNum] as $h): ?>
        <div class="aula" style="background:<?= $p['cor'] ?>26; border-left:3px solid <?= $p['cor'] ?>">
          <strong><?= substr($h['hora_inicio'],0,5) ?>–<?= substr($h['hora_fim'],0,5) ?></strong><br>
          <?= htmlspecialchars($h['disciplina_nome']) ?><br>
          <small>
            <?= htmlspecialchars($h['curso_nome'] . ' – ' . $h['turma_nome']) ?>
            <?= $h['sala_nome'] ? ' · ' . htmlspecialchars($h['sala_nome']) : '' ?>
          </small>
        </div>
        <?php endforeach; endif; ?>
      </td>
      <?php endforeach; ?>
    </tr>
  </table>
</div>
<?php endforeach; ?>

<?php if (empty($porProfessor)): ?>
<p>Nenhum horário nesta geração.</p>
<?php endif; ?>

</body>
</html>
