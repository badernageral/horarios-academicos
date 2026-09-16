-- Vínculo do professor: Efetivo ou Substituto.
--
-- Padrão "Efetivo" para não exigir preenchimento em bancos já existentes
-- (era o único tipo de vínculo antes deste campo existir).
ALTER TABLE professores ADD COLUMN vinculo TEXT NOT NULL DEFAULT 'Efetivo';
