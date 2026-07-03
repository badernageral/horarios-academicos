
    </div><!-- /.content-wrapper -->
</div><!-- /#page-content -->
</div><!-- /#wrapper -->

<!-- Modal Gerar Horário (global, disponível em todas as páginas) -->
<div class="modal fade" id="modalGerar" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="<?= $base ?>/horarios/gerar">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-magic me-2"></i>Gerar Horário Automático</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Descrição da Geração</label>
          <input type="text" name="descricao" class="form-control"
                 value="Geração <?= date('d/m/Y H:i') ?>" placeholder="Ex: Semestre 2025.1">
        </div>
        <hr>
        <p class="fw-semibold mb-2">Pesos das Restrições Soft <small class="text-muted fw-normal">(valores maiores = mais importante)</small></p>
        <div class="row g-2">
          <?php
          $pesosDb = \App\Core\Database::fetchAll("SELECT * FROM configuracoes_soft ORDER BY chave");
          foreach ($pesosDb as $p): ?>
          <div class="col-md-6">
            <label class="form-label small mb-1"><?= htmlspecialchars($p['nome']) ?>
              <span class="text-muted"><?= htmlspecialchars($p['descricao'] ?? '') ?></span>
            </label>
            <input type="number" step="0.5" min="0" max="10000"
                   name="pesos[<?= $p['chave'] ?>]"
                   value="<?= $p['valor'] ?>"
                   class="form-control form-control-sm">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-play-fill me-1"></i>Iniciar Geração
        </button>
      </div>
    </div>
    </form>
  </div>
</div>

<script src="<?= $base ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= $base ?>/assets/js/app.js"></script>
</body>
</html>
