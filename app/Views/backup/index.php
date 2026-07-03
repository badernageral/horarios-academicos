<?php $pageTitle = 'Backup'; ?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= $flash['message'] ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex align-items-center mb-3">
  <h5 class="mb-0 fw-semibold"><i class="bi bi-shield-check me-2 text-primary"></i>Backup do Banco</h5>
</div>

<div class="row g-3">
  <!-- Exportar -->
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-download me-1"></i>Exportar backup
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted">
          Gera um dump completo do banco (estrutura + dados) via <code>mysqldump</code>,
          salva uma cópia em <code>backups/</code> e baixa o arquivo <code>.sql</code>.
        </p>
        <a href="<?= $base ?>/backup/exportar" class="btn btn-primary mt-auto">
          <i class="bi bi-download me-1"></i>Baixar backup agora
        </a>
      </div>
    </div>
  </div>

  <!-- Importar -->
  <div class="col-md-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-upload me-1"></i>Importar backup
      </div>
      <div class="card-body">
        <div class="alert alert-warning small py-2">
          <i class="bi bi-exclamation-triangle me-1"></i>
          A importação <strong>substitui todos os dados atuais</strong> pelo conteúdo do arquivo.
          Por segurança, o estado atual é salvo automaticamente em <code>backups/</code> antes de sobrescrever.
        </div>
        <form method="POST" action="<?= $base ?>/backup/importar" enctype="multipart/form-data"
              onsubmit="return confirm('Isto vai SUBSTITUIR todos os dados atuais pelo arquivo enviado. Deseja continuar?');">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Arquivo de backup (.sql)</label>
            <input type="file" name="arquivo" accept=".sql" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-upload me-1"></i>Importar e substituir
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Backups recentes -->
<?php if (!empty($arquivos)): ?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header bg-transparent fw-semibold">
    <i class="bi bi-clock-history me-1"></i>Backups recentes em <code>backups/</code>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr><th class="small">Arquivo</th><th class="small text-end">Tamanho</th><th class="small">Data</th></tr>
        </thead>
        <tbody>
          <?php foreach ($arquivos as $a): ?>
          <tr>
            <td class="small"><code><?= htmlspecialchars($a['nome']) ?></code></td>
            <td class="small text-end"><?= number_format($a['tamanho'] / 1024, 1) ?> KB</td>
            <td class="small text-muted"><?= date('d/m/Y H:i', $a['data']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
