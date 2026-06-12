/* SGA – Main JS */

document.addEventListener('DOMContentLoaded', function () {
  // ── Auto-dismiss flash alerts ─────────────────────────────────
  document.querySelectorAll('.alert.fade.show').forEach(function (el) {
    setTimeout(function () {
      const bsAlert = bootstrap.Alert.getInstance(el) || new bootstrap.Alert(el);
      bsAlert.close();
    }, 5000);
  });

  // ── Initialize all Bootstrap tooltips ─────────────────────────
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    new bootstrap.Tooltip(el);
  });


  // ── Loading state on generation form ──────────────────────────
  const gerarForm = document.querySelector('#modalGerar form');
  if (gerarForm) {
    gerarForm.addEventListener('submit', function () {
      const btn = gerarForm.querySelector('[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando...';
      }
    });
  }
});

// ── Highlight active nav link ───────────────────────────────────
(function () {
  const path = window.location.pathname;
  document.querySelectorAll('#sidebar .nav-link').forEach(function (link) {
    const href = link.getAttribute('href');
    if (href && href !== '/' && path.startsWith(href)) {
      link.classList.add('active');
    } else if (href === '/' && path === '/') {
      link.classList.add('active');
    }
  });
})();
