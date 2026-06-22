-- ============================================================
-- Sistema de Geração Automática de Horários Acadêmicos
-- Schema v1.1 | MySQL 8.0+ / MariaDB 10.6+
-- Horários baseados em tempo real (hora_inicio / hora_fim)
-- Sem períodos fixos
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

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

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────────────────────
-- CURSOS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE cursos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome           VARCHAR(255) NOT NULL,
    descricao      TEXT,
    turno_inicio   TIME NOT NULL DEFAULT '07:00:00',
    turno_fim           TIME NOT NULL DEFAULT '12:00:00',
    duracao_aula_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'Duração de 1 aula em minutos',
    -- JSON array de inteiros: 1=Seg, 2=Ter, 3=Qua, 4=Qui, 5=Sex, 6=Sab
    dias_semana    JSON NOT NULL,
    cor            CHAR(7) NOT NULL DEFAULT '#3b82f6' COMMENT 'Cor hex para visualização',
    ativo          TINYINT(1) NOT NULL DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- INTERVALOS POR CURSO (breaks configuráveis)
-- NULL em dia_semana = aplica a todos os dias do curso
-- ─────────────────────────────────────────────────────────────
CREATE TABLE intervalos_curso (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id     INT UNSIGNED NOT NULL,
    dia_semana   TINYINT UNSIGNED NULL COMMENT 'NULL = todos os dias',
    hora_inicio  TIME NOT NULL,
    hora_fim     TIME NOT NULL,
    descricao    VARCHAR(100) DEFAULT 'Intervalo',
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    INDEX idx_curso_dia (curso_id, dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- NDAs (Núcleos de Desenvolvimento de Área)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE ndas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(255) NOT NULL,
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- SALAS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE salas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- PROFESSORES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE professores (
    id                         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                       VARCHAR(255) NOT NULL,
    matricula                  VARCHAR(50) NOT NULL UNIQUE,
    email                      VARCHAR(255),
    carga_horaria_diaria_max   SMALLINT UNSIGNED NOT NULL DEFAULT 360 COMMENT 'em minutos',
    carga_horaria_semanal_max  SMALLINT UNSIGNED NOT NULL DEFAULT 1200 COMMENT 'em minutos',
    nda_id                     INT UNSIGNED NULL,
    cor                        CHAR(7) NOT NULL DEFAULT '#3b82f6' COMMENT 'Cor primária para identificação visual',
    cor_secundaria             CHAR(7) NOT NULL DEFAULT '#f97316' COMMENT 'Cor secundária contrastante para identificação visual',
    ativo                      TINYINT(1) NOT NULL DEFAULT 1,
    created_at                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (nda_id) REFERENCES ndas(id) ON DELETE SET NULL,
    INDEX idx_nda (nda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- DISPONIBILIDADE DO PROFESSOR por dia e janela de tempo
-- ─────────────────────────────────────────────────────────────
CREATE TABLE disponibilidade_professor (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    professor_id  INT UNSIGNED NOT NULL,
    dia_semana    TINYINT UNSIGNED NOT NULL COMMENT '1=Segunda..6=Sábado',
    hora_inicio   TIME NOT NULL,
    hora_fim      TIME NOT NULL,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    INDEX idx_prof_dia (professor_id, dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- QUALIFICAÇÕES DO PROFESSOR (disciplinas que pode lecionar)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE professor_qualificacao (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    professor_id  INT UNSIGNED NOT NULL,
    disciplina_nome VARCHAR(255) NOT NULL,
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_prof_disc (professor_id, disciplina_nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TURMAS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE turmas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id      INT UNSIGNED NOT NULL,
    nome          VARCHAR(100) NOT NULL,
    serie_periodo VARCHAR(50) NOT NULL,
    qtd_alunos    SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    ano_letivo    YEAR NOT NULL DEFAULT 2025,
    ativo         TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (curso_id) REFERENCES cursos(id),
    INDEX idx_curso (curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- DISCIPLINAS
-- carga_horaria_semanal = qtd_encontros_semanais × duracao_encontro_minutos
-- ─────────────────────────────────────────────────────────────
CREATE TABLE disciplinas (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                     VARCHAR(255) NOT NULL,
    sigla                    VARCHAR(50),
    curso_id                 INT UNSIGNED NOT NULL,
    turma_id                 INT UNSIGNED NOT NULL,
    professor_id             INT UNSIGNED NULL,
    qtd_encontros_semanais   TINYINT UNSIGNED NOT NULL DEFAULT 2,
    qtd_aulas                TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Aulas por encontro (× duracao_aula_minutos do curso)',
    qtd_aulas_ead            TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Aulas EaD por semana (contam na carga semanal, não ocupam horário-relógio na grade)',
    qtd_professores          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Número de professores para esta disciplina',
    semestre_oferta          TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Bitmask: 1=1º sem, 2=2º sem, 3=ambos',
    cor                      CHAR(7) NOT NULL DEFAULT '#6366f1',
    ativo                    TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (curso_id) REFERENCES cursos(id),
    FOREIGN KEY (turma_id) REFERENCES turmas(id),
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE SET NULL,
    INDEX idx_turma (turma_id),
    INDEX idx_professor (professor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- SEMESTRES (agrupamentos de disciplinas para geração)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE semestres (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    semestre    TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 ou 2',
    ano         YEAR NOT NULL,
    descricao   VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- ATRIBUIÇÕES POR SEMESTRE (professor → disciplina neste semestre)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE semestre_atribuicoes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    semestre_id   INT UNSIGNED NOT NULL,
    disciplina_id INT UNSIGNED NOT NULL,
    professor_id  INT UNSIGNED NOT NULL,
    slot          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Posição do professor (1=principal, 2=secundário...)',
    sala_id       INT UNSIGNED NULL COMMENT 'Sala preferencial (slot 1)',
    FOREIGN KEY (semestre_id)   REFERENCES semestres(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    FOREIGN KEY (professor_id)  REFERENCES professores(id),
    FOREIGN KEY (sala_id)       REFERENCES salas(id) ON DELETE SET NULL,
    UNIQUE KEY uq_sem_disc_slot (semestre_id, disciplina_id, slot),
    INDEX idx_semestre (semestre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- ENSALAMENTO POR SEMESTRE (sala padrão por turma)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE semestre_salas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    semestre_id INT UNSIGNED NOT NULL,
    turma_id    INT UNSIGNED NOT NULL,
    sala_id     INT UNSIGNED NOT NULL,
    FOREIGN KEY (semestre_id) REFERENCES semestres(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id)    REFERENCES turmas(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id)     REFERENCES salas(id),
    UNIQUE KEY uq_sem_turma (semestre_id, turma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- GERAÇÕES (execuções do algoritmo)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE geracoes (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    semestre_id           INT UNSIGNED NULL,
    descricao             VARCHAR(255),
    status                ENUM('pendente','processando','concluido','parcial','erro') NOT NULL DEFAULT 'pendente',
    total_atividades      INT UNSIGNED DEFAULT 0,
    atividades_agendadas  INT UNSIGNED DEFAULT 0,
    atividades_falhas     INT UNSIGNED DEFAULT 0,
    configuracao          JSON,
    log                   TEXT,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at           TIMESTAMP NULL,
    FOREIGN KEY (semestre_id) REFERENCES semestres(id) ON DELETE CASCADE,
    INDEX idx_semestre (semestre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- HORÁRIOS GERADOS
-- hora_inicio e hora_fim são tempos reais, sem períodos fixos
-- ─────────────────────────────────────────────────────────────
CREATE TABLE horarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    geracao_id      INT UNSIGNED NOT NULL,
    disciplina_id   INT UNSIGNED NOT NULL,
    turma_id        INT UNSIGNED NOT NULL,
    professor_id    INT UNSIGNED NOT NULL,
    sala_id         INT UNSIGNED NULL,
    dia_semana      TINYINT UNSIGNED NOT NULL COMMENT '0=Limbo (sem horário) 1=Seg 2=Ter 3=Qua 4=Qui 5=Sex 6=Sab',
    hora_inicio     TIME NOT NULL,
    hora_fim        TIME NOT NULL,
    FOREIGN KEY (geracao_id)    REFERENCES geracoes(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id),
    FOREIGN KEY (turma_id)      REFERENCES turmas(id),
    FOREIGN KEY (professor_id)  REFERENCES professores(id),
    FOREIGN KEY (sala_id)       REFERENCES salas(id),
    INDEX idx_geracao (geracao_id),
    INDEX idx_turma_dia (turma_id, dia_semana),
    INDEX idx_prof_dia (professor_id, dia_semana),
    INDEX idx_sala_dia (sala_id, dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- PESOS DAS RESTRIÇÕES SOFT (configuráveis)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE configuracoes_soft (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave      VARCHAR(100) NOT NULL UNIQUE,
    nome       VARCHAR(200) NOT NULL,
    valor      DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    descricao  TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes_soft (chave, nome, valor, descricao) VALUES
('janela_professor',    'Janelas do Professor',          10.0, 'Penalidade por janela (tempo livre entre aulas) para professores'),
('janela_turma',        'Janelas da Turma',               8.0, 'Penalidade por janela (tempo livre entre aulas) para turmas'),
('agrupa_disciplina',   'Agrupar Disciplina no Dia',      5.0, 'Penalidade por colocar dois encontros da mesma disciplina no mesmo dia'),
('distribuicao_semana', 'Distribuição Semanal',           3.0, 'Penalidade por concentrar muitas aulas em um único dia'),
('horario_extremo',     'Evitar Horários Extremos',       2.0, 'Penalidade por horários muito cedo ou muito tarde'),
('balancear_professor', 'Balancear Carga do Professor',   4.0, 'Penalidade por desbalancear a carga diária do professor');
