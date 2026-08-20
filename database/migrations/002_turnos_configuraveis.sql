-- Turnos passam a ser configuráveis pela interface (/configuracoes).
--
-- Antes as faixas viviam em config/app.php. Agora ficam aqui, para o usuário
-- ajustar sem editar código. As CHAVES são fixas ('matutino'/'vespertino'/
-- 'noturno') porque disponibilidade_professor.turno referencia esses valores;
-- o que a tela edita é o nome e as horas de cada um.
CREATE TABLE IF NOT EXISTS turnos (
    chave       TEXT PRIMARY KEY,
    nome        TEXT NOT NULL,
    hora_inicio TEXT NOT NULL,
    hora_fim    TEXT NOT NULL,
    ordem       INTEGER NOT NULL DEFAULT 0
);

INSERT OR IGNORE INTO turnos (chave, nome, hora_inicio, hora_fim, ordem) VALUES
    ('matutino',   'Matutino',   '07:00:00', '12:00:00', 1),
    ('vespertino', 'Vespertino', '12:00:00', '18:00:00', 2),
    ('noturno',    'Noturno',    '18:00:00', '23:00:00', 3);
