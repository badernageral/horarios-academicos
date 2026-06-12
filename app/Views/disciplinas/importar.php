<?php $pageTitle = 'Importar Disciplinas em Massa'; ?>

<div class="d-flex align-items-center mb-3">
  <a href="/disciplinas" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold">Importar Disciplinas em Massa</h5>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
  <?= htmlspecialchars($flash['message']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold">Lista de Disciplinas</div>
      <div class="card-body">
        <form method="POST" action="/disciplinas/importar">

          <div class="mb-3">
            <label class="form-label">Semestre de Oferta <span class="text-danger">*</span></label>
            <select name="semestre_oferta" class="form-select" required>
              <option value="3" selected>Ambos (anual)</option>
              <option value="1">1º Semestre</option>
              <option value="2">2º Semestre</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Turma <span class="text-danger">*</span></label>
            <select name="turma_id" class="form-select" required>
              <option value="">Selecione a turma...</option>
              <?php foreach ($turmas as $t): ?>
              <option value="<?= $t['id'] ?>">
                <?= htmlspecialchars($t['curso_nome'] . ' – ' . $t['serie_periodo']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Todas as disciplinas da lista serão vinculadas a esta turma.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">
              Nome - Aulas por encontro — uma por linha <span class="text-danger">*</span>
            </label>
            <textarea name="linhas" id="linhas" class="form-control font-monospace"
                      rows="14"
                      placeholder="Matemática - 2&#10;Português - 2&#10;Física - 1&#10;Educação Física"
                      required></textarea>
            <div class="form-text">
              Separe o nome e a quantidade de aulas por encontro com <strong>-</strong>.
              Se omitir o <strong>-</strong>, assume <strong>2 aulas/encontro</strong>.
            </div>
          </div>

          <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-warning">
              <i class="bi bi-cloud-upload me-1"></i>Cadastrar Disciplinas
            </button>
            <span id="contadorLinhas" class="text-muted small">0 disciplinas</span>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-transparent fw-semibold">Padrões aplicados</div>
      <div class="card-body">
        <ul class="list-unstyled mb-0 small">
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Sigla: primeiros 20 caracteres do nome (maiúsculas)</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Encontros semanais: 1</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Aulas por encontro: valor após o <strong>-</strong>, ou 2 se omitido</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Número de professores: 1</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Oferta: semestre selecionado no formulário</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Status: Ativa</li>
          <li class="mb-2"><i class="bi bi-info-circle text-primary me-2"></i>Turma: selecionada no topo do formulário</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
const ta = document.getElementById('linhas');
const contador = document.getElementById('contadorLinhas');
function atualizar() {
  const n = ta.value.split('\n').filter(l => l.trim() !== '').length;
  contador.textContent = n + (n === 1 ? ' disciplina' : ' disciplinas');
}
ta.addEventListener('input', atualizar);
</script>
