-- Disponibilidade do professor por TURNO, não mais por faixa de horário.
--
-- A tela do professor passou a ser uma grade de 3 turnos x 5 dias com 3 estados
-- por retângulo. O banco não guarda mais hora_inicio/hora_fim: guarda o turno.
-- As faixas de hora de cada turno vivem em config/app.php ('turnos'), que é a
-- fonte única consultada pelo gerador.
--
--   estado = 1  -> verde: pode dar aula (preferencial)
--   estado = 2  -> interrogação: só se não houver verde livre
--   vermelho    -> ausência de linha (segue sendo a forma de dizer "não pode")
--
-- Bancos anteriores: todos os professores existentes recebem disponibilidade
-- TOTAL (os 15 retângulos verdes), que é o estado de fato do sistema hoje.

CREATE TABLE IF NOT EXISTS disponibilidade_professor_nova (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    professor_id INTEGER NOT NULL,
    dia_semana   INTEGER NOT NULL,          -- 1=Segunda ... 5=Sexta
    turno        TEXT    NOT NULL,          -- 'matutino' | 'vespertino' | 'noturno'
    estado       INTEGER NOT NULL DEFAULT 1,
    UNIQUE (professor_id, dia_semana, turno),
    FOREIGN KEY (professor_id) REFERENCES professores(id) ON DELETE CASCADE
);

INSERT OR IGNORE INTO disponibilidade_professor_nova (professor_id, dia_semana, turno, estado)
SELECT p.id, d.dia, t.turno, 1
FROM professores p
CROSS JOIN (SELECT 1 AS dia UNION ALL SELECT 2 UNION ALL SELECT 3
            UNION ALL SELECT 4 UNION ALL SELECT 5) d
CROSS JOIN (SELECT 'matutino' AS turno UNION ALL SELECT 'vespertino'
            UNION ALL SELECT 'noturno') t;

DROP TABLE IF EXISTS disponibilidade_professor;
ALTER TABLE disponibilidade_professor_nova RENAME TO disponibilidade_professor;
CREATE INDEX IF NOT EXISTS idx_disp_prof_dia ON disponibilidade_professor(professor_id, dia_semana);
