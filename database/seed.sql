-- ============================================================
-- Dados de exemplo para demonstração
-- ============================================================

-- Cursos
INSERT INTO cursos (nome, descricao, turno_inicio, turno_fim, dias_semana, cor) VALUES
('Técnico em Informática',  'Curso técnico integrado em informática', '07:00:00', '12:00:00', '["1","2","3","4","5"]', '#3b82f6'),
('Técnico em Agricultura',  'Curso técnico integrado em agropecuária','13:00:00', '18:00:00', '["1","2","3","4","5"]', '#22c55e'),
('Agronomia',               'Bacharelado em Agronomia',               '07:00:00', '12:00:00', '["1","2","3","4","5","6"]', '#f59e0b');

-- Intervalos (sem períodos fixos - tempo real)
-- Informática: intervalo 09:30-09:50
INSERT INTO intervalos_curso (curso_id, dia_semana, hora_inicio, hora_fim, descricao) VALUES
(1, NULL, '09:30:00', '09:50:00', 'Intervalo manhã'),
-- Agricultura: intervalo 15:30-15:50
(2, NULL, '15:30:00', '15:50:00', 'Intervalo tarde'),
-- Agronomia: intervalo 09:30-09:50 e sábado sem intervalo
(3, 1, '09:30:00', '09:50:00', 'Intervalo manhã'),
(3, 2, '09:30:00', '09:50:00', 'Intervalo manhã'),
(3, 3, '09:30:00', '09:50:00', 'Intervalo manhã'),
(3, 4, '09:30:00', '09:50:00', 'Intervalo manhã'),
(3, 5, '09:30:00', '09:50:00', 'Intervalo manhã');

-- Salas
INSERT INTO salas (nome, capacidade, tipo) VALUES
('Sala 101',            40, 'sala'),
('Sala 102',            40, 'sala'),
('Sala 103',            35, 'sala'),
('Lab. Informática 1',  30, 'laboratorio'),
('Lab. Informática 2',  30, 'laboratorio'),
('Lab. Agricultura',    25, 'laboratorio'),
('Auditório',          100, 'auditorio');

-- Professores
INSERT INTO professores (nome, matricula, email, carga_horaria_diaria_max, carga_horaria_semanal_max) VALUES
('Ana Paula Souza',    'PROF001', 'ana@escola.edu.br',    360, 1200),
('Carlos Eduardo',     'PROF002', 'carlos@escola.edu.br', 300, 1000),
('Fernanda Lima',      'PROF003', 'fernanda@escola.edu.br',360, 1200),
('Roberto Mendes',     'PROF004', 'roberto@escola.edu.br', 300, 1000),
('Patrícia Oliveira',  'PROF005', 'patricia@escola.edu.br',360, 1200),
('João Bosco',         'PROF006', 'joao@escola.edu.br',   240, 900),
('Maria Clara',        'PROF007', 'maria@escola.edu.br',  360, 1200);

-- Disponibilidade dos professores (Segunda a Sexta, horário integral)
INSERT INTO disponibilidade_professor (professor_id, dia_semana, hora_inicio, hora_fim)
SELECT p.id, d.dia, '07:00:00', '18:00:00'
FROM professores p
CROSS JOIN (SELECT 1 AS dia UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) d;

-- Qualificações
INSERT INTO professor_qualificacao (professor_id, disciplina_nome) VALUES
(1, 'Algoritmos'), (1, 'Programação'), (1, 'Estrutura de Dados'),
(2, 'Redes de Computadores'), (2, 'Sistemas Operacionais'),
(3, 'Matemática'), (3, 'Física'),
(4, 'Agronomia Geral'), (4, 'Solos'), (4, 'Culturas'),
(5, 'Química'), (5, 'Biologia'),
(6, 'Português'), (6, 'Literatura'),
(7, 'Inglês'), (7, 'História');

-- Turmas
INSERT INTO turmas (curso_id, nome, serie_periodo, qtd_alunos, ano_letivo) VALUES
(1, 'INF-1A', '1º Ano', 35, 2025),
(1, 'INF-2A', '2º Ano', 32, 2025),
(1, 'INF-3A', '3º Ano', 30, 2025),
(2, 'AGR-1A', '1º Ano', 28, 2025),
(2, 'AGR-2A', '2º Ano', 26, 2025),
(3, 'AGRO-1', '1º Período', 40, 2025);

-- Disciplinas (usando tempos reais, sem períodos fixos)
-- Informática 1º Ano
INSERT INTO disciplinas (nome, codigo, curso_id, turma_id, professor_id, tipo_sala_requerido, qtd_encontros_semanais, duracao_encontro_minutos, cor) VALUES
('Algoritmos',              'ALG101', 1, 1, 1, 'laboratorio', 4, 45,  '#3b82f6'),
('Matemática',              'MAT101', 1, 1, 3, 'qualquer',    3, 60,  '#8b5cf6'),
('Português',               'POR101', 1, 1, 6, 'qualquer',    3, 50,  '#ec4899'),
('Inglês',                  'ING101', 1, 1, 7, 'qualquer',    2, 50,  '#f97316'),
('Física',                  'FIS101', 1, 1, 3, 'qualquer',    2, 60,  '#06b6d4'),
('Redes',                   'RED101', 1, 1, 2, 'laboratorio', 2, 50,  '#10b981'),
-- Informática 2º Ano
('Programação',             'PRG201', 1, 2, 1, 'laboratorio', 4, 45,  '#3b82f6'),
('Estrutura de Dados',      'ESD201', 1, 2, 1, 'laboratorio', 3, 60,  '#6366f1'),
('Sistemas Operacionais',   'SOS201', 1, 2, 2, 'laboratorio', 2, 50,  '#10b981'),
('Matemática',              'MAT201', 1, 2, 3, 'qualquer',    3, 60,  '#8b5cf6'),
('Química',                 'QUI201', 1, 2, 5, 'qualquer',    2, 50,  '#f59e0b'),
-- Informática 3º Ano
('Redes de Computadores',   'RED301', 1, 3, 2, 'laboratorio', 3, 50,  '#10b981'),
('Programação Web',         'WEB301', 1, 3, 1, 'laboratorio', 4, 45,  '#3b82f6'),
('Inglês Técnico',          'ING301', 1, 3, 7, 'qualquer',    2, 50,  '#f97316'),
-- Agricultura 1º Ano
('Agronomia Geral',         'AGR101', 2, 4, 4, 'qualquer',    3, 50,  '#22c55e'),
('Solos',                   'SOL101', 2, 4, 4, 'laboratorio', 2, 60,  '#84cc16'),
('Química',                 'QUI101', 2, 4, 5, 'qualquer',    3, 50,  '#f59e0b'),
('Matemática',              'MAT102', 2, 4, 3, 'qualquer',    2, 50,  '#8b5cf6'),
('Biologia',                'BIO101', 2, 4, 5, 'qualquer',    2, 50,  '#34d399'),
-- Agricultura 2º Ano
('Culturas',                'CUL201', 2, 5, 4, 'qualquer',    3, 60,  '#22c55e'),
('Solos Avançado',          'SOL201', 2, 5, 4, 'laboratorio', 2, 90,  '#84cc16'),
('Química Agrícola',        'QUA201', 2, 5, 5, 'qualquer',    2, 50,  '#f59e0b'),
-- Agronomia 1º Período
('Introdução à Agronomia',  'IAG101', 3, 6, 4, 'qualquer',    2, 60,  '#f59e0b'),
('Biologia Geral',          'BIO111', 3, 6, 5, 'qualquer',    3, 60,  '#34d399'),
('Matemática Superior',     'MAT111', 3, 6, 3, 'qualquer',    4, 60,  '#8b5cf6');
