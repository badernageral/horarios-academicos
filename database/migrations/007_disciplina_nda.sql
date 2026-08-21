-- Vínculo opcional da disciplina com um NDA.
--
-- Nullable de propósito: diferente do professor, onde o NDA é obrigatório,
-- aqui é só uma classificação. Disciplinas já cadastradas ficam sem vínculo.
ALTER TABLE disciplinas ADD COLUMN nda_id INTEGER REFERENCES ndas(id) ON DELETE SET NULL;
