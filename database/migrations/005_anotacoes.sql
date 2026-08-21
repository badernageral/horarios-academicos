-- Anotações na grade: "Reposição do dia 10/02" e afins.
--
-- Duas naturezas, ambas presas à GERAÇÃO (não ao cadastro): a anotação vale
-- para aquela semana montada. Como cada geração substitui a anterior
-- (DELETE FROM geracoes), as anotações somem junto — que é o comportamento
-- desejado para um horário pontual.
--
-- 1) No bloco da disciplina: coluna em `horarios`. Fica na própria linha, então
--    acompanha o bloco quando ele é arrastado para outro dia/horário.
ALTER TABLE horarios ADD COLUMN observacao TEXT;

-- 2) No nome da turma: tabela própria, porque não há uma linha única de turma
--    dentro da geração. O CASCADE limpa quando a geração é substituída.
CREATE TABLE IF NOT EXISTS anotacoes_turma (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    geracao_id INTEGER NOT NULL,
    turma_id   INTEGER NOT NULL,
    texto      TEXT    NOT NULL,
    UNIQUE (geracao_id, turma_id),
    FOREIGN KEY (geracao_id) REFERENCES geracoes(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id)   REFERENCES turmas(id)   ON DELETE CASCADE
);
