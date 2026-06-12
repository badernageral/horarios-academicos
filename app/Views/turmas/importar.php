<?php $pageTitle = 'Importar Turmas em Massa'; ?>

<div class="d-flex align-items-center mb-3">
  <a href="/turmas" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold">Importar Turmas em Massa</h5>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold">Dados</div>
      <div class="card-body">
        <form method="POST" action="/turmas/importar">

          <div class="mb-3">
            <label class="form-label">Curso <span class="text-danger">*</span></label>
            <select name="curso_id" class="form-select" required>
              <option value="">Selecione...</option>
              <?php foreach ($cursos as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Todas as turmas desta importação serão vinculadas a este curso.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Séries / Períodos <span class="text-danger">*</span></label>
            <textarea name="series" id="series" class="form-control font-monospace"
                      rows="10" placeholder="1º Ano&#10;2º Ano&#10;3º Ano"
                      required></textarea>
            <div class="form-text">Uma série ou período por linha. Linhas em branco são ignoradas.</div>
          </div>

          <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-info text-white">
              <i class="bi bi-cloud-upload me-1"></i>Cadastrar Turmas
            </button>
            <span id="contadorTurmas" class="text-muted small">0 turmas</span>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold">Padrões aplicados</div>
      <div class="card-body">
        <ul class="list-unstyled mb-0 small">
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Curso conforme selecionado</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Status: Ativa</li>
          <li class="mb-2"><i class="bi bi-info-circle text-primary me-2"></i>Cada linha vira uma turma com a série/período informada</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
const ta = document.getElementById('series');
const contador = document.getElementById('contadorTurmas');
function atualizar() {
  const n = ta.value.split('\n').filter(l => l.trim() !== '').length;
  contador.textContent = n + (n === 1 ? ' turma' : ' turmas');
}
ta.addEventListener('input', atualizar);
</script>
