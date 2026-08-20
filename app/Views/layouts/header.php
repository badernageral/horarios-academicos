<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Horários Acadêmicos') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📅</text></svg>">
    <link href="<?= $base ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $base ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<div class="d-flex" id="wrapper">
<nav id="sidebar" class="d-flex flex-column flex-shrink-0 p-0">
    <a href="<?= $base ?>/" class="sidebar-brand d-flex align-items-center px-3 py-3 text-decoration-none">
        <i class="bi bi-calendar2-week-fill me-2 fs-4"></i>
        <span class="fw-bold" style="line-height:1.1">Horários<br>Acadêmicos</span>
    </a>
    <hr class="sidebar-divider m-0">

    <ul class="nav flex-column px-2 mt-2 flex-grow-1">
        <li class="nav-item">
            <a href="<?= $base ?>/" class="nav-link <?= in_array(REQUEST_PATH,['/','/dashboard']) ? 'active':'' ?>">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= $base ?>/cursos" class="nav-link <?= str_starts_with(REQUEST_PATH,'/cursos')?'active':'' ?>">
                <i class="bi bi-mortarboard me-2"></i> Cursos
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/ndas" class="nav-link <?= str_starts_with(REQUEST_PATH,'/ndas')?'active':'' ?>">
                <i class="bi bi-diagram-3 me-2"></i> NDAs
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/professores" class="nav-link <?= str_starts_with(REQUEST_PATH,'/professores')?'active':'' ?>">
                <i class="bi bi-person-badge me-2"></i> Professores
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/turmas" class="nav-link <?= str_starts_with(REQUEST_PATH,'/turmas')?'active':'' ?>">
                <i class="bi bi-people me-2"></i> Turmas
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/disciplinas" class="nav-link <?= str_starts_with(REQUEST_PATH,'/disciplinas')?'active':'' ?>">
                <i class="bi bi-book me-2"></i> Disciplinas
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/salas" class="nav-link <?= str_starts_with(REQUEST_PATH,'/salas')?'active':'' ?>">
                <i class="bi bi-door-open me-2"></i> Salas
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= $base ?>/horarios" class="nav-link <?= str_starts_with(REQUEST_PATH,'/horarios')?'active':'' ?>">
                <i class="bi bi-calendar3 me-2"></i> Horários
            </a>
        </li>

        <li class="nav-item mt-2">
            <a href="<?= $base ?>/usuarios" class="nav-link <?= str_starts_with(REQUEST_PATH,'/usuarios')?'active':'' ?>">
                <i class="bi bi-people me-2"></i> Usuários
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/configuracoes" class="nav-link <?= str_starts_with(REQUEST_PATH,'/configuracoes')?'active':'' ?>">
                <i class="bi bi-sliders me-2"></i> Configurações
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/backup" class="nav-link <?= str_starts_with(REQUEST_PATH,'/backup')?'active':'' ?>">
                <i class="bi bi-shield-check me-2"></i> Backup
            </a>
        </li>
    </ul>

    <div class="px-3 py-2 mt-auto sidebar-footer small text-muted">
        Horários Acadêmicos &bull; v1.0
    </div>
</nav>

<!-- Page content -->
<div id="page-content" class="flex-grow-1">
    <!-- Topbar -->
    <nav class="navbar navbar-expand-lg topbar px-3 py-2 d-flex justify-content-between">
        <span class="navbar-brand mb-0 fw-semibold text-dark">
            <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
        </span>
        <?php $authUser = \App\Core\Auth::user(); if ($authUser): ?>
        <div class="dropdown">
            <a href="#" class="text-decoration-none text-dark dropdown-toggle d-flex align-items-center"
               data-bs-toggle="dropdown">
                <i class="bi bi-person-circle fs-5 me-1"></i>
                <span class="small fw-semibold"><?= htmlspecialchars($authUser['nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted">@<?= htmlspecialchars($authUser['usuario']) ?></span></li>
                <li><a class="dropdown-item" href="<?= $base ?>/usuarios/<?= $authUser['id'] ?>/editar">
                    <i class="bi bi-key me-2"></i>Minha conta</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= $base ?>/logout">
                    <i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>

    <div class="content-wrapper p-3 p-lg-4">
