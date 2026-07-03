<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — SGA</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📅</text></svg>">
    <link href="<?= $base ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/css/app.css" rel="stylesheet">
    <style>
        body { background:#0f172a; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .login-card { width:100%; max-width:380px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center text-white mb-4">
            <i class="bi bi-calendar2-week-fill fs-1"></i>
            <h4 class="fw-bold mt-2 mb-0">SGA</h4>
            <small class="text-white-50">Horários Acadêmicos</small>
        </div>

        <div class="card border-0 shadow">
            <div class="card-body p-4">
                <?php if (!empty($erro)): ?>
                <div class="alert alert-<?= $erro['type'] ?> py-2 small mb-3">
                    <?= htmlspecialchars($erro['message']) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= $base ?>/login">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Usuário</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="usuario" class="form-control" autofocus required
                                   autocomplete="username" placeholder="admin">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="senha" class="form-control" required
                                   autocomplete="current-password" placeholder="••••••">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
