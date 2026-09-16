<?php $atualizacao = \App\Services\UpdateChecker::verificar(); ?>
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
    <script>
      // Aplica o estado do menu ANTES da renderização, para não piscar aberto e fechar em seguida.
      if (localStorage.getItem('sgaSidebarCollapsed') === '1') {
        document.documentElement.classList.add('sidebar-collapsed');
      }
    </script>
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
            <a href="<?= $base ?>/" class="nav-link <?= in_array(REQUEST_PATH,['/','/painel']) ? 'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Painel">
                <i class="bi bi-speedometer2 me-2"></i> <span>Painel</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= $base ?>/cursos" class="nav-link <?= str_starts_with(REQUEST_PATH,'/cursos')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Cursos">
                <i class="bi bi-mortarboard me-2"></i> <span>Cursos</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/ndas" class="nav-link <?= str_starts_with(REQUEST_PATH,'/ndas')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="NDAs">
                <i class="bi bi-diagram-3 me-2"></i> <span>NDAs</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/professores" class="nav-link <?= str_starts_with(REQUEST_PATH,'/professores')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Professores">
                <i class="bi bi-person-badge me-2"></i> <span>Professores</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/turmas" class="nav-link <?= str_starts_with(REQUEST_PATH,'/turmas')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Turmas">
                <i class="bi bi-people me-2"></i> <span>Turmas</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/disciplinas" class="nav-link <?= str_starts_with(REQUEST_PATH,'/disciplinas')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Disciplinas">
                <i class="bi bi-book me-2"></i> <span>Disciplinas</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/salas" class="nav-link <?= str_starts_with(REQUEST_PATH,'/salas')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Salas">
                <i class="bi bi-door-open me-2"></i> <span>Salas</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= $base ?>/horarios" class="nav-link <?= str_starts_with(REQUEST_PATH,'/horarios')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Horários">
                <i class="bi bi-calendar3 me-2"></i> <span>Horários</span>
            </a>
        </li>

        <li class="nav-item mt-2">
            <a href="<?= $base ?>/usuarios" class="nav-link <?= str_starts_with(REQUEST_PATH,'/usuarios')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Usuários">
                <i class="bi bi-people me-2"></i> <span>Usuários</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/configuracoes" class="nav-link <?= str_starts_with(REQUEST_PATH,'/configuracoes')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Configurações">
                <i class="bi bi-sliders me-2"></i> <span>Configurações</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/backup" class="nav-link <?= str_starts_with(REQUEST_PATH,'/backup')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Backup">
                <i class="bi bi-shield-check me-2"></i> <span>Backup</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= $base ?>/atualizacoes" class="nav-link <?= str_starts_with(REQUEST_PATH,'/atualizacoes')?'active':'' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Atualizações">
                <i class="bi bi-arrow-repeat me-2"></i> <span>Atualizações</span>
                <?php if ($atualizacao): ?>
                    <i class="bi bi-circle-fill text-warning ms-1" style="font-size:.4rem;vertical-align:middle" title="Nova versão disponível"></i>
                <?php endif; ?>
            </a>
        </li>
    </ul>

    <div class="px-2 pb-2">
        <button type="button" id="sidebarToggle" class="btn btn-sm w-100 d-flex align-items-center justify-content-center sidebar-toggle-btn" title="Recolher menu">
            <i class="bi bi-chevron-double-left"></i>
        </button>
    </div>

    <div class="px-3 py-2 sidebar-footer small text-muted">
        Horários Acadêmicos &bull; v<?= htmlspecialchars($config['version']) ?>
        <?php if ($atualizacao): ?>
            <a href="<?= $base ?>/atualizacoes" class="d-block mt-1 text-decoration-none" title="Ver detalhes da atualização">
                <i class="bi bi-arrow-up-circle-fill me-1"></i>Nova versão v<?= htmlspecialchars($atualizacao['versao_disponivel']) ?>
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- Page content -->
<div id="page-content" class="flex-grow-1">
    <!-- Topbar -->
    <nav class="navbar navbar-expand-lg topbar px-3 py-2 d-flex justify-content-between">
        <span class="navbar-brand mb-0 fw-semibold text-dark">
            <?= htmlspecialchars($pageTitle ?? 'Painel') ?>
        </span>
        <?php $authUser = \App\Core\Auth::user(); if ($authUser): ?>
        <div class="dropdown">
            <a href="#" class="text-decoration-none text-dark dropdown-toggle d-flex align-items-center"
               data-bs-toggle="dropdown">
                <?php if ($atualizacao): ?>
                    <i class="bi bi-arrow-up-circle-fill text-warning me-2" data-bs-toggle="tooltip" data-bs-placement="bottom"
                       title="Nova versão v<?= htmlspecialchars($atualizacao['versao_disponivel']) ?> disponível"></i>
                <?php endif; ?>
                <i class="bi bi-person-circle fs-5 me-1"></i>
                <span class="small fw-semibold"><?= htmlspecialchars($authUser['nome']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted">@<?= htmlspecialchars($authUser['usuario']) ?></span></li>
                <?php if ($atualizacao): ?>
                    <li><a class="dropdown-item text-warning-emphasis" href="<?= $base ?>/atualizacoes">
                        <i class="bi bi-arrow-up-circle-fill me-2"></i>Nova versão v<?= htmlspecialchars($atualizacao['versao_disponivel']) ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
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
