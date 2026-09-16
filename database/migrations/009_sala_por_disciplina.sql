-- Sala escolhida por disciplina no semestre, independente de já haver
-- professor atribuído.
--
-- Antes a sala vivia grudada na linha do professor do slot 1, dentro de
-- semestre_atribuicoes (professor_id é NOT NULL lá) — então a sala escolhida
-- se perdia silenciosamente quando a disciplina ainda não tinha professor.
CREATE TABLE IF NOT EXISTS semestre_disciplina_salas (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    semestre_id   INTEGER NOT NULL,
    disciplina_id INTEGER NOT NULL,
    sala_id       INTEGER NOT NULL,
    FOREIGN KEY (semestre_id)   REFERENCES semestres(id) ON DELETE CASCADE,
    FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id)       REFERENCES salas(id),
    UNIQUE (semestre_id, disciplina_id)
);
CREATE INDEX IF NOT EXISTS idx_sem_disc_salas_semestre ON semestre_disciplina_salas(semestre_id);

-- Backfill: traz para a tabela nova as salas que já estavam atribuídas
-- (grudadas no professor do slot 1), para não mudar nada do que já existe.
INSERT OR IGNORE INTO semestre_disciplina_salas (semestre_id, disciplina_id, sala_id)
SELECT semestre_id, disciplina_id, sala_id
FROM semestre_atribuicoes
WHERE slot = 1 AND sala_id IS NOT NULL;
