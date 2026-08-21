-- Lacunas da turma: turnos em que ela NÃO tem aula.
--
-- Mesmo desenho da disponibilidade do professor (3 turnos x 5 dias), mas com
-- apenas dois estados. A presença da linha significa "pode ter aula"; a
-- AUSÊNCIA significa bloqueado — mesma convenção já usada em
-- disponibilidade_professor, para não haver duas regras diferentes no projeto.
--
-- Ex.: turma sem aula na quarta à tarde = sem linha (quarta, vespertino).
-- Disciplina que não couber nos turnos liberados vai para o limbo.
--
-- Bancos anteriores: toda turma existente nasce liberada nos 15 turnos, que é
-- o comportamento de hoje (nenhuma restrição).
CREATE TABLE IF NOT EXISTS disponibilidade_turma (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    turma_id   INTEGER NOT NULL,
    dia_semana INTEGER NOT NULL,          -- 1=Segunda ... 5=Sexta
    turno      TEXT    NOT NULL,          -- 'matutino' | 'vespertino' | 'noturno'
    UNIQUE (turma_id, dia_semana, turno),
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_disp_turma_dia ON disponibilidade_turma(turma_id, dia_semana);

INSERT OR IGNORE INTO disponibilidade_turma (turma_id, dia_semana, turno)
SELECT t.id, d.dia, tn.turno
FROM turmas t
CROSS JOIN (SELECT 1 AS dia UNION ALL SELECT 2 UNION ALL SELECT 3
            UNION ALL SELECT 4 UNION ALL SELECT 5) d
CROSS JOIN (SELECT 'matutino' AS turno UNION ALL SELECT 'vespertino'
            UNION ALL SELECT 'noturno') tn;
