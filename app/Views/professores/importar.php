<?php $pageTitle = 'Importar Professores em Massa'; ?>

<div class="d-flex align-items-center mb-3">
  <a href="<?= $base ?>/professores" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
  <h5 class="mb-0 fw-semibold">Importar Professores em Massa</h5>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold">Lista de Nomes</div>
      <div class="card-body">
        <form method="POST" action="<?= $base ?>/professores/importar">
          <div class="mb-3">
            <label class="form-label">Nome - NDA — uma entrada por linha <span class="text-danger">*</span></label>
            <textarea name="nomes" id="nomes" class="form-control font-monospace"
                      rows="14" placeholder="Maria da Silva - Ciências Exatas&#10;João Pedro Souza - Linguagens&#10;Ana Paula Lima - Agrárias"
                      required></textarea>
            <div class="form-text">
              Separe o nome do professor e o NDA com <strong>-</strong>. Linhas em branco são ignoradas.
              Se o NDA não for encontrado, a linha será pulada.
            </div>
          </div>

          <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-success">
              <i class="bi bi-cloud-upload me-1"></i>Cadastrar Professores
            </button>
            <span id="contadorNomes" class="text-muted small">0 nomes</span>
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
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Disponibilidade: Seg–Sex, 07:00–23:00</li>
          <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Status: Ativo</li>
          <li class="mb-2"><i class="bi bi-info-circle text-primary me-2"></i>Cores atribuídas em sequência</li>
          <li class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Linhas sem NDA válido são puladas</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
const ta = document.getElementById('nomes');
const contador = document.getElementById('contadorNomes');
function atualizarContador() {
  const n = ta.value.split('\n').filter(l => l.trim() !== '').length;
  contador.textContent = n + (n === 1 ? ' nome' : ' nomes');
}
ta.addEventListener('input', atualizarContador);
</script>
