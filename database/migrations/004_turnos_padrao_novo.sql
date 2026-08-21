-- Novos horários padrão dos turnos: 07:00–12:30 / 12:30–18:30 / 18:30–23:00.
--
-- Atualiza SOMENTE as linhas que ainda estão nos valores antigos (semeados
-- pela 002 ou pelo schema.sql). Quem já ajustou os turnos em /configuracoes
-- mantém o que configurou — uma migration não deve desfazer escolha do usuário.
UPDATE turnos SET hora_inicio = '07:00:00', hora_fim = '12:30:00'
 WHERE chave = 'matutino'   AND hora_inicio = '07:00:00' AND hora_fim = '12:00:00';

UPDATE turnos SET hora_inicio = '12:30:00', hora_fim = '18:30:00'
 WHERE chave = 'vespertino' AND hora_inicio = '12:00:00' AND hora_fim = '18:00:00';

UPDATE turnos SET hora_inicio = '18:30:00', hora_fim = '23:00:00'
 WHERE chave = 'noturno'    AND hora_inicio = '18:00:00' AND hora_fim = '23:00:00';
