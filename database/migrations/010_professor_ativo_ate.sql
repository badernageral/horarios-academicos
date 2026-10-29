-- "Ativo até": último semestre (ano + período) em que o professor leciona.
--
-- Fim de contrato sem precisar inativar: o professor continua aparecendo nos
-- semestres até o informado (onde já está atribuído) e some dos seguintes.
-- NULL = sem data de saída, que é o estado de todo professor existente — a
-- migration só acrescenta colunas, nenhum dado é alterado.
ALTER TABLE professores ADD COLUMN ativo_ate_ano INTEGER;
ALTER TABLE professores ADD COLUMN ativo_ate_semestre INTEGER;
