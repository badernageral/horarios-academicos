-- ============================================================
-- Sistema de Geração Automática de Horários Acadêmicos
-- Schema v2 | SQLite 3.24+
-- Horários baseados em tempo real (hora_inicio / hora_fim em TEXT "HH:MM:SS")
-- (versão MySQL histórica em database/schema.mysql.sql)
-- ============================================================

PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS horarios;
DROP TABLE IF EXISTS geracoes;
DROP TABLE IF EXISTS semestre_salas;
DROP TABLE IF EXISTS semestre_atribuicoes;
DROP TABLE IF EXISTS semestres;
DROP TABLE IF EXISTS configuracoes_soft;
DROP TABLE IF EXISTS disciplinas;
DROP TABLE IF EXISTS professor_qualificacao;
DROP TABLE IF EXISTS disponibilidade_professor;
DROP TABLE IF EXISTS turmas;
DROP TABLE IF EXISTS intervalos_curso;
DROP TABLE IF EXISTS salas;
DROP TABLE IF EXISTS professores;
DROP TABLE IF EXISTS ndas;
DROP TABLE IF EXISTS cursos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS schema_migrations;

-- ── CURSOS ────────────────────────────────────────────────────────
-- dias_semana: JSON array em TEXT (ex.: "[1,2,3,4,5]"); horas em TEXT "HH:MM:SS"
CREATE TABLE cursos (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    nome                 TEXT NOT NULL,
    descricao            TEXT,
    turno_inicio         TEXT NOT NULL DEFAULT '07:00:00',
    turno_fim            TEXT NOT NULL DEFAULT '12:00:00',
    duracao_aula_minutos INTEGER NOT NULL DEFAULT 50,
    dias_semana          TEXT NOT NULL,
    cor                  TEXT NOT NULL DEFAULT '#3b82f6',
    ativo                INTEGER NOT NULL DEFAULT 1,
    created_at           TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at           TEXT DEFAULT CURRENT_TIMESTAMP
);

-- ── INTERVALOS POR CURSO (dia_semana NULL = todos os dias) ────────
CREATE TABLE intervalos_curso (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    curso_id    INTEGER NOT NULL,
    dia_semana  INTEGER,
    hora_inicio TEXT NOT NULL,
    hora_fim    TEXT NOT NULL,
    descricao   TEXT DEFAULT 'Intervalo',
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
);
CREATE INDEX idx_intervalos_curso_dia ON intervalos_curso(curso_id, dia_semana);

-- ── NDAs ──────────────────────────────────────────────────────────
CREATE TABLE ndas (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    nome       TEXT NOT NULL,
    ativo      INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- ── SALAS ─────────────────────────────────────────────────────────
CREATE TABLE salas (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    nome       TEXT NOT NULL,
    ativo      INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- ── PROFESSORES ───────────────────────────────────────────────────
CREATE TABLE professores (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    nome                      TEXT NOT NULL,
    matricula                 TEXT NOT NULL UNIQUE,
    email                     TEXT,
    usuario_moodle            TEXT,
    carga_horaria_diaria_max  INTEGER NOT NULL DEFAULT 360,
    carga_horaria_semanal_max INTEGER NOT NULL DEFAULT 1200,
    nda_id                    INTEGER,
    cor                       TEXT NOT NULL DEFAULT '#3b82f6',
    cor_secundaria            TEXT NOT NULL DEFAULT '#f97316',
    ativo                     INTEGER NOT NULL DEFAULT 1,
    created_at                TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at                TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nda_id) REFERENCES ndas(id) ON DELETE SET NULL
);
CREATE INDEX idx_prof_nda ON professores(nda_id);

-- ── DISPONIBILIDADE DO PROFESSOR ──────────────────────────────────
CREATE TABLE disponibilidade_professor (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    professor_id INTEGER NOT NULL,
    dia_semana   INTEGER NOT NULL,
    hora_inicio  TEXT NOT NULL,
    hora_fim     TEXT NOT NULL,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE
);
CREATE INDEX idx_disp_prof_dia ON disponibilidade_professor(professor_id, dia_semana);

-- ── QUALIFICAÇÕES DO PROFESSOR ────────────────────────────────────
CREATE TABLE professor_qualificacao (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    professor_id    INTEGER NOT NULL,
    disciplina_nome TEXT NOT NULL,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    UNIQUE (professor_id, disciplina_nome)
);

-- ── TURMAS ────────────────────────────────────────────────────────
CREATE TABLE turmas (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    curso_id      INTEGER NOT NULL,
    nome          TEXT NOT NULL,
    serie_periodo TEXT NOT NULL,
    qtd_alunos    INTEGER NOT NULL DEFAULT 30,
    ano_letivo    INTEGER NOT NULL DEFAULT 2025,
    ativo         INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (curso_id) REFERENCES cursos(id)
);
CREATE INDEX idx_turmas_curso ON turmas(curso_id);

-- ── DISCIPLINAS ───────────────────────────────────────────────────
CREATE TABLE disciplinas (
    id                     INTEGER PRIMARY KEY AUTOINCREMENT,
    nome                   TEXT NOT NULL,
    sigla                  TEXT,
    curso_id               INTEGER NOT NULL,
    turma_id               INTEGER NOT NULL,
    professor_id           INTEGER,
    qtd_encontros_semanais INTEGER NOT NULL DEFAULT 2,
    qtd_aulas              INTEGER NOT NULL DEFAULT 2,
    qtd_aulas_ead          INTEGER NOT NULL DEFAULT 0,
    qtd_professores        INTEGER NOT NULL DEFAULT 1,
    semestre_oferta        INTEGER NOT NULL DEFAULT 3,
    cor                    TEXT NOT NULL DEFAULT '#6366f1',
    ativo                  INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (curso_id) REFERENCES cursos(id),
    FOREIGN KEY (turma_id) REFERENCES turmas(id),
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE SET NULL
);
CREATE INDEX idx_disc_turma ON disciplinas(turma_id);
CREATE INDEX idx_disc_professor ON disciplinas(professor_id);

-- ── SEMESTRES ─────────────────────────────────────────────────────
CREATE TABLE semestres (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    semestre   INTEGER NOT NULL DEFAULT 1,
    ano        INTEGER NOT NULL,
    descricao  TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- ── ATRIBUIÇÕES POR SEMESTRE ──────────────────────────────────────
CREATE TABLE semestre_atribuicoes (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    semestre_id   INTEGER NOT NULL,
    disciplina_id INTEGER NOT NULL,
    professor_id  INTEGER NOT NULL,
    slot          INTEGER NOT NULL DEFAULT 1,
    sala_id       INTEGER,
    FOREIGN KEY (semestre_id)   REFERENCES semestres(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id)  REFERENCES professores(id),
    FOREIGN KEY (sala_id)       REFERENCES salas(id) ON DELETE SET NULL,
    UNIQUE (semestre_id, disciplina_id, slot)
);
CREATE INDEX idx_atrib_semestre ON semestre_atribuicoes(semestre_id);

-- ── ENSALAMENTO POR SEMESTRE ──────────────────────────────────────
CREATE TABLE semestre_salas (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    semestre_id INTEGER NOT NULL,
    turma_id    INTEGER NOT NULL,
    sala_id     INTEGER NOT NULL,
    FOREIGN KEY (semestre_id) REFERENCES semestres(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id)    REFERENCES turmas(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id)     REFERENCES salas(id),
    UNIQUE (semestre_id, turma_id)
);

-- ── GERAÇÕES ──────────────────────────────────────────────────────
-- status: 'pendente' | 'processando' | 'concluido' | 'parcial' | 'erro'
CREATE TABLE geracoes (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    semestre_id          INTEGER,
    descricao            TEXT,
    status               TEXT NOT NULL DEFAULT 'pendente',
    total_atividades     INTEGER DEFAULT 0,
    atividades_agendadas INTEGER DEFAULT 0,
    atividades_falhas    INTEGER DEFAULT 0,
    configuracao         TEXT,
    log                  TEXT,
    created_at           TEXT DEFAULT CURRENT_TIMESTAMP,
    finished_at          TEXT,
    FOREIGN KEY (semestre_id) REFERENCES semestres(id) ON DELETE CASCADE
);
CREATE INDEX idx_ger_semestre ON geracoes(semestre_id);

-- ── HORÁRIOS GERADOS ──────────────────────────────────────────────
CREATE TABLE horarios (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    geracao_id    INTEGER NOT NULL,
    disciplina_id INTEGER NOT NULL,
    turma_id      INTEGER NOT NULL,
    professor_id  INTEGER NOT NULL,
    sala_id       INTEGER,
    dia_semana    INTEGER NOT NULL,
    hora_inicio   TEXT NOT NULL,
    hora_fim      TEXT NOT NULL,
    FOREIGN KEY (geracao_id)    REFERENCES geracoes(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id),
    FOREIGN KEY (turma_id)      REFERENCES turmas(id),
    FOREIGN KEY (professor_id)  REFERENCES professores(id),
    FOREIGN KEY (sala_id)       REFERENCES salas(id)
);
CREATE INDEX idx_hor_geracao   ON horarios(geracao_id);
CREATE INDEX idx_hor_turma_dia ON horarios(turma_id, dia_semana);
CREATE INDEX idx_hor_prof_dia  ON horarios(professor_id, dia_semana);
CREATE INDEX idx_hor_sala_dia  ON horarios(sala_id, dia_semana);

-- ── PESOS DAS RESTRIÇÕES SOFT ─────────────────────────────────────
CREATE TABLE configuracoes_soft (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    chave     TEXT NOT NULL UNIQUE,
    nome      TEXT NOT NULL,
    valor     REAL NOT NULL DEFAULT 1.00,
    descricao TEXT
);

INSERT INTO configuracoes_soft (chave, nome, valor, descricao) VALUES
('janela_professor',    'Janelas do Professor',          10.0, 'Penalidade por janela (tempo livre entre aulas) para professores'),
('janela_turma',        'Janelas da Turma',               8.0, 'Penalidade por janela (tempo livre entre aulas) para turmas'),
('agrupa_disciplina',   'Agrupar Disciplina no Dia',      5.0, 'Penalidade por colocar dois encontros da mesma disciplina no mesmo dia'),
('distribuicao_semana', 'Distribuição Semanal',           3.0, 'Penalidade por concentrar muitas aulas em um único dia'),
('horario_extremo',     'Evitar Horários Extremos',       2.0, 'Penalidade por horários muito cedo ou muito tarde'),
('balancear_professor', 'Balancear Carga do Professor',   4.0, 'Penalidade por desbalancear a carga diária do professor'),
('compactar_professor', 'Compactar Semana do Professor',  2500.0, 'Penalidade por agendar aula em dia distante dos dias em que o professor já leciona (agrupa a semana em dias consecutivos)'),
('preferencia_dia2',    'Proximidade do 2º Dia do Professor', 2000.0, 'Penalidade por abrir o segundo dia de aula do professor longe do primeiro (evita seg+sex; prefere dias adjacentes)');

-- ── USUÁRIOS ──────────────────────────────────────────────────────
CREATE TABLE usuarios (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    nome       TEXT NOT NULL,
    usuario    TEXT NOT NULL UNIQUE,
    senha_hash TEXT NOT NULL,
    ativo      INTEGER NOT NULL DEFAULT 1,
    criado_em  TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Sem usuário padrão: na 1ª execução, sem nenhum usuário cadastrado, o sistema
-- redireciona para /setup e oferece o cadastro do primeiro usuário.

-- ── CONTROLE DE MIGRATIONS ────────────────────────────────────────
CREATE TABLE schema_migrations (
    migration  TEXT NOT NULL PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
