<?php

/** @var \App\Core\Router $router */

// ── Dashboard ─────────────────────────────────────────────────────
$router->get('/',           'DashboardController@index');
$router->get('/dashboard',  'DashboardController@index');

// ── Professores ───────────────────────────────────────────────────
$router->get('/professores',              'ProfessoresController@index');
$router->get('/professores/novo',         'ProfessoresController@novo');
$router->post('/professores/salvar',      'ProfessoresController@salvar');
$router->get('/professores/{id}/editar',  'ProfessoresController@editar');
$router->post('/professores/deletar',     'ProfessoresController@deletar');

// ── Cursos ────────────────────────────────────────────────────────
$router->get('/cursos',              'CursosController@index');
$router->get('/cursos/novo',         'CursosController@novo');
$router->post('/cursos/salvar',      'CursosController@salvar');
$router->get('/cursos/{id}/editar',  'CursosController@editar');
$router->post('/cursos/deletar',     'CursosController@deletar');

// ── Turmas ────────────────────────────────────────────────────────
$router->get('/turmas',              'TurmasController@index');
$router->get('/turmas/nova',         'TurmasController@nova');
$router->post('/turmas/salvar',      'TurmasController@salvar');
$router->get('/turmas/{id}/editar',  'TurmasController@editar');
$router->post('/turmas/deletar',     'TurmasController@deletar');

// ── Disciplinas ───────────────────────────────────────────────────
$router->get('/disciplinas',              'DisciplinasController@index');
$router->get('/disciplinas/nova',         'DisciplinasController@nova');
$router->post('/disciplinas/salvar',      'DisciplinasController@salvar');
$router->get('/disciplinas/{id}/editar',  'DisciplinasController@editar');
$router->post('/disciplinas/deletar',     'DisciplinasController@deletar');

// ── Salas ─────────────────────────────────────────────────────────
$router->get('/salas',              'SalasController@index');
$router->get('/salas/nova',         'SalasController@nova');
$router->post('/salas/salvar',      'SalasController@salvar');
$router->get('/salas/{id}/editar',  'SalasController@editar');
$router->post('/salas/deletar',     'SalasController@deletar');

// ── Horários (visualização e geração) ────────────────────────────
$router->get('/horarios',                              'HorariosController@index');
$router->get('/horarios/novo',                         'HorariosController@novo');
$router->post('/horarios/salvar',                      'HorariosController@salvar');
$router->get('/horarios/{id}/editar',                  'HorariosController@editar');
$router->get('/horarios/{id}/detalhe',                 'HorariosController@detalhe');
$router->get('/horarios/{id}/atribuir',                'HorariosController@verAtribuir');
$router->post('/horarios/{id}/atribuir',               'HorariosController@atribuir');
$router->get('/horarios/{id}/ensalamento',             'HorariosController@verEnsalamento');
$router->post('/horarios/{id}/ensalamento',            'HorariosController@ensalar');
$router->get('/horarios/{id}/gerar',                   'HorariosController@verGerar');
$router->post('/horarios/{id}/gerar',                  'HorariosController@gerar');
$router->post('/horarios/deletar',                     'HorariosController@deletar');
$router->get('/horarios/geracao/{id}/turma',           'HorariosController@verTurma');
$router->get('/horarios/geracao/{id}/professor',       'HorariosController@verProfessor');
$router->get('/horarios/geracao/{id}/sala',            'HorariosController@verSala');
$router->get('/horarios/geracao/{id}/exportar/csv',    'HorariosController@exportarCSV');
$router->get('/horarios/geracao/{id}/exportar/excel',  'HorariosController@exportarExcel');
$router->get('/horarios/geracao/{id}/exportar/pdf',    'HorariosController@exportarPDF');
$router->post('/horarios/geracao/deletar',             'HorariosController@deletarGeracao');
$router->get('/horarios/geracao/{id}/grade',           'HorariosController@verGrade');
$router->post('/horarios/geracao/mover',               'HorariosController@moverHorario');

// ── API REST ──────────────────────────────────────────────────────
$router->get('/api/geracoes',                                'ApiController@listarGeracoes');
$router->get('/api/geracao/{id}',                            'ApiController@geracao');
$router->post('/api/gerar',                                  'ApiController@gerar');
$router->get('/api/conflitos/{id}',                          'ApiController@conflitos');
$router->get('/api/horarios/turma/{turma}/{geracao}',        'ApiController@horariosTurma');
$router->get('/api/horarios/professor/{prof}/{geracao}',     'ApiController@horariosProfessor');
$router->get('/api/horarios/sala/{sala}/{geracao}',          'ApiController@horariosSala');
$router->get('/api/disciplinas',                             'ApiController@listaDisciplinas');
$router->get('/api/professores',                             'ApiController@listaProfessores');
$router->get('/api/estatisticas/{id}',                       'ApiController@estatisticas');
