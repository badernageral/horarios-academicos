<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primeiro acesso — Horários Acadêmicos</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📅</text></svg>">
    <link href="<?= $base ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/css/app.css" rel="stylesheet">
    <style>
        body { background:#0f172a; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .login-card { width:100%; max-width:420px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center text-white mb-4">
            <i class="bi bi-calendar2-week-fill fs-1"></i>
            <h4 class="fw-bold mt-2 mb-0">Horários Acadêmicos</h4>
            <small class="text-white-50">Primeiro acesso</small>
        </div>

        <div class="card border-0 shadow">
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Nenhum usuário cadastrado ainda. Crie o <strong>primeiro usuário</strong>
                    (acesso total) para começar.
                </p>

                <?php if (!empty($erro)): ?>
                <div class="alert alert-<?= $erro['type'] ?> py-2 small mb-3">
                    <?= htmlspecialchars($erro['message']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($avisoMigracao)): ?>
                <div class="alert alert-<?= htmlspecialchars($avisoMigracao['tipo']) ?> py-2 small">
                    <?= htmlspecialchars($avisoMigracao['texto']) ?>
                </div>
                <?php endif; ?>


                <form method="POST" action="<?= $base ?>/setup">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nome</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text" name="nome" class="form-control" autofocus required
                                   placeholder="Seu nome">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Usuário</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="usuario" class="form-control" required
                                   autocomplete="username" placeholder="ex: admin">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="senha" class="form-control" required
                                   autocomplete="new-password" placeholder="mín. 4 caracteres">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Confirmar senha</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="senha_confirma" class="form-control" required
                                   autocomplete="new-password" placeholder="repita a senha">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i>Criar usuário e entrar
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
