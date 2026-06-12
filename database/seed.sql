-- ============================================================
-- Dados de exemplo para demonstração
-- ============================================================

-- Cursos
INSERT INTO cursos (nome, descricao, turno_inicio, turno_fim, duracao_aula_minutos, dias_semana, cor) VALUES
('Técnico em Informática',  'Curso técnico integrado em informática',  '07:00:00', '12:00:00', 50, '[1,2,3,4,5]', '#3b82f6'),
('Técnico em Agricultura',  'Curso técnico integrado em agropecuária', '13:00:00', '18:00:00', 50, '[1,2,3,4,5]', '#22c55e'),
('Agronomia',               'Bacharelado em Agronomia',                '07:00:00', '12:00:00', 60, '[1,2,3,4,5,6]', '#f59e0b');

-- Intervalos (tempo real, sem períodos fixos)
INSERT INTO intervalos_curso (curso_id, dia_semana, hora_inicio, hora_fim, descricao) VALUES
(1, NULL, '09:30:00', '09:50:00', 'Intervalo manhã'),
(2, NULL, '15:30:00', '15:50:00', 'Intervalo tarde'),
(3, 1, '09:30:00', '09:50:00', 'Intervalo manhã'),
(3, 2, '09:30:00', '09:50:00', 'Intervalo manhã'),
(3, 3, '09:30:00', '09:50:00', 'Intervalo manhã'),
(3, 4, '09:30:00', '09:50:00', 'Intervalo manhã'),
(3, 5, '09:30:00', '09:50:00', 'Intervalo manhã');

-- Salas
INSERT INTO salas (nome, ativo) VALUES
('Sala 101',           1),
('Sala 102',           1),
('Sala 103',           1),
('Lab. Informática 1', 1),
('Lab. Informática 2', 1),
('Lab. Agricultura',   1),
('Auditório',          1);

-- Professores
INSERT INTO professores (nome, matricula, email, carga_horaria_diaria_max, carga_horaria_semanal_max, cor) VALUES
('Ana Paula Souza',    'PROF001', 'ana@escola.edu.br',      360, 1200, '#3b82f6'),
('Carlos Eduardo',     'PROF002', 'carlos@escola.edu.br',   300, 1000, '#6366f1'),
('Fernanda Lima',      'PROF003', 'fernanda@escola.edu.br', 360, 1200, '#8b5cf6'),
('Roberto Mendes',     'PROF004', 'roberto@escola.edu.br',  300, 1000, '#22c55e'),
('Patrícia Oliveira',  'PROF005', 'patricia@escola.edu.br', 360, 1200, '#f59e0b'),
('João Bosco',         'PROF006', 'joao@escola.edu.br',     240,  900, '#ec4899'),
('Maria Clara',        'PROF007', 'maria@escola.edu.br',    360, 1200, '#14b8a6');

-- Disponibilidade: Segunda a Sexta, horário integral
INSERT INTO disponibilidade_professor (professor_id, dia_semana, hora_inicio, hora_fim)
SELECT p.id, d.dia, '07:00:00', '18:00:00'
FROM professores p
CROSS JOIN (SELECT 1 AS dia UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) d;

-- Turmas
INSERT INTO turmas (curso_id, nome, serie_periodo, qtd_alunos, ano_letivo) VALUES
(1, 'INF-1A', '1º Ano',     35, 2025),
(1, 'INF-2A', '2º Ano',     32, 2025),
(1, 'INF-3A', '3º Ano',     30, 2025),
(2, 'AGR-1A', '1º Ano',     28, 2025),
(2, 'AGR-2A', '2º Ano',     26, 2025),
(3, 'AGRO-1', '1º Período', 40, 2025);

-- Disciplinas
-- qtd_aulas = aulas por encontro × duracao_aula_minutos do curso = duração do encontro
-- Informática (50min/aula): qtd_aulas=1 → 50min, qtd_aulas=2 → 100min
-- Informática 1º Ano
INSERT INTO disciplinas (nome, sigla, curso_id, turma_id, qtd_encontros_semanais, qtd_aulas, semestre_oferta, cor) VALUES
('Algoritmos',            'ALG', 1, 1, 4, 1, 3, '#3b82f6'),
('Matemática',            'MAT', 1, 1, 3, 1, 3, '#8b5cf6'),
('Português',             'POR', 1, 1, 3, 1, 3, '#ec4899'),
('Inglês',                'ING', 1, 1, 2, 1, 3, '#f97316'),
('Física',                'FIS', 1, 1, 2, 1, 3, '#06b6d4'),
('Redes',                 'RED', 1, 1, 2, 1, 3, '#10b981'),
-- Informática 2º Ano
('Programação',           'PRG', 1, 2, 4, 1, 3, '#3b82f6'),
('Estrutura de Dados',    'ESD', 1, 2, 3, 1, 3, '#6366f1'),
('Sistemas Operacionais', 'SOS', 1, 2, 2, 1, 3, '#10b981'),
('Matemática',            'MAT', 1, 2, 3, 1, 3, '#8b5cf6'),
('Química',               'QUI', 1, 2, 2, 1, 3, '#f59e0b'),
-- Informática 3º Ano
('Redes de Computadores', 'RED', 1, 3, 3, 1, 3, '#10b981'),
('Programação Web',       'WEB', 1, 3, 4, 1, 3, '#3b82f6'),
('Inglês Técnico',        'ING', 1, 3, 2, 1, 3, '#f97316'),
-- Agricultura 1º Ano
('Agronomia Geral',       'AGR', 2, 4, 3, 1, 3, '#22c55e'),
('Solos',                 'SOL', 2, 4, 2, 1, 3, '#84cc16'),
('Química',               'QUI', 2, 4, 3, 1, 3, '#f59e0b'),
('Matemática',            'MAT', 2, 4, 2, 1, 3, '#8b5cf6'),
('Biologia',              'BIO', 2, 4, 2, 1, 3, '#34d399'),
-- Agricultura 2º Ano
('Culturas',              'CUL', 2, 5, 3, 1, 3, '#22c55e'),
('Solos Avançado',        'SOL', 2, 5, 2, 2, 3, '#84cc16'),
('Química Agrícola',      'QUA', 2, 5, 2, 1, 3, '#f59e0b'),
-- Agronomia 1º Período (60min/aula)
('Introdução à Agronomia', 'IAG', 3, 6, 2, 1, 3, '#f59e0b'),
('Biologia Geral',         'BIO', 3, 6, 3, 1, 3, '#34d399'),
('Matemática Superior',    'MAT', 3, 6, 4, 1, 3, '#8b5cf6');

-- Semestre de demonstração
INSERT INTO semestres (semestre, ano, descricao) VALUES (1, 2025, '1º Semestre / 2025');

-- Atribuições: professores às disciplinas para o 1º semestre/2025
-- disciplinas da turma INF-1A (ids 1–6)
INSERT INTO semestre_atribuicoes (semestre_id, disciplina_id, professor_id) VALUES
(1,  1, 1),  -- Algoritmos      → Ana Paula
(1,  2, 3),  -- Matemática INF1 → Fernanda
(1,  3, 6),  -- Português       → João Bosco
(1,  4, 7),  -- Inglês          → Maria Clara
(1,  5, 3),  -- Física          → Fernanda
(1,  6, 2),  -- Redes INF1      → Carlos Eduardo
-- INF-2A (ids 7–11)
(1,  7, 1),  -- Programação     → Ana Paula
(1,  8, 1),  -- Estrutura Dados → Ana Paula
(1,  9, 2),  -- Sist. Operac.   → Carlos Eduardo
(1, 10, 3),  -- Matemática INF2 → Fernanda
(1, 11, 5),  -- Química INF2    → Patrícia
-- INF-3A (ids 12–14)
(1, 12, 2),  -- Redes Comp.     → Carlos Eduardo
(1, 13, 1),  -- Prog. Web       → Ana Paula
(1, 14, 7),  -- Inglês Técnico  → Maria Clara
-- AGR-1A (ids 15–19)
(1, 15, 4),  -- Agronomia Geral → Roberto
(1, 16, 4),  -- Solos           → Roberto
(1, 17, 5),  -- Química AGR1    → Patrícia
(1, 18, 3),  -- Matemática AGR1 → Fernanda
(1, 19, 5),  -- Biologia AGR1   → Patrícia
-- AGR-2A (ids 20–22)
(1, 20, 4),  -- Culturas        → Roberto
(1, 21, 4),  -- Solos Avançado  → Roberto
(1, 22, 5),  -- Química Agrícola→ Patrícia
-- AGRO-1 (ids 23–25)
(1, 23, 4),  -- Intro Agronomia → Roberto
(1, 24, 5),  -- Biologia Geral  → Patrícia
(1, 25, 3);  -- Matemática Sup. → Fernanda
