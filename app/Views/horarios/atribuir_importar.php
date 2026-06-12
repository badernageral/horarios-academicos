<?php
$pageTitle     = 'Importar Atribuições em Massa';
$semestreLabel = $semestre['semestre'] . 'º Semestre / ' . $semestre['ano'];
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="/horarios/<?= $semestreId ?>/atribuir" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i>
  </a>
  <div>
    <h5 class="mb-0 fw-semibold"><i class="bi bi-cloud-upload me-2 text-success"></i>Importar Atribuições em Massa</h5>
    <small class="text-muted"><?= $semestreLabel ?></small>
  </div>
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
      <div class="card-header bg-transparent fw-semibold">Lista de Atribuições</div>
      <div class="card-body">
        <form method="POST" action="/horarios/<?= $semestreId ?>/atribuir/importar">
          <div class="mb-3">
            <label class="form-label">
              Disciplina - Professor — uma por linha <span class="text-danger">*</span>
            </label>
            <textarea name="linhas" id="linhas" class="form-control font-monospace"
                      rows="16"
                      placeholder="Matemática - João da Silva&#10;Matemática - Maria Oliveira&#10;Português - Carlos Souza&#10;Cálculo I - Diferencial - Ana Paula Lima"
                      required></textarea>
            <div class="form-text">
              Separe o nome da disciplina e o professor com <strong> - </strong> (espaço, hífen, espaço).
              O separador usado é o <strong>último</strong> " - " da linha, então disciplinas com hífen no nome funcionam normalmente.
            </div>
          </div>

          <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-check-lg me-1"></i>Salvar Atribuições
            </button>
            <span id="contadorLinhas" class="text-muted small">0 linhas</span>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-transparent fw-semibold">Como funciona</div>
      <div class="card-body small">
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Repita a disciplina em linhas consecutivas para atribuir mais de um professor (1ª linha = Prof. 1, 2ª linha = Prof. 2…)</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Se já houver atribuição no slot, <strong>substitui</strong> o professor</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Nomes buscados sem distinção de maiúsculas/minúsculas</li>
          <li class="mb-2"><i class="bi bi-info-circle text-primary me-2"></i>Se a mesma disciplina existir em várias turmas do semestre, todas são atribuídas</li>
          <li class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Linhas sem correspondência de disciplina ou professor são puladas e reportadas</li>
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
  contador.textContent = n + (n === 1 ? ' linha' : ' linhas');
}
ta.addEventListener('input', atualizar);
</script>
