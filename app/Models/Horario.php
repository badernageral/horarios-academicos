<?php

namespace App\Models;

use App\Core\Database;

class Horario extends BaseModel
{
    protected static string $table = 'horarios';

    /**
     * Situação REAL da geração, contada na tabela `horarios`.
     *
     * As colunas `atividades_agendadas`/`atividades_falhas`/`status` em
     * `geracoes` são um retrato do instante em que o gerador rodou; arrastar um
     * bloco para fora do limbo depois NÃO as atualiza. Para exibição usamos
     * esta contagem, senão a tela continua dizendo "parcial" com o limbo vazio.
     *
     * Devolve null quando a geração ainda não tem horários (em processamento ou
     * erro) — nesse caso o status gravado é o que vale.
     *
     * @return array{agendadas:int, nao_agendadas:int, status:string}|null
     */
    public static function situacao(int $geracaoId): ?array
    {
        $r = Database::fetchOne(
            "SELECT SUM(CASE WHEN dia_semana <> 0 THEN 1 ELSE 0 END) AS agendadas,
                    SUM(CASE WHEN dia_semana =  0 THEN 1 ELSE 0 END) AS limbo,
                    COUNT(*) AS total
             FROM horarios WHERE geracao_id = ?",
            [$geracaoId]
        );

        if (!$r || (int)$r['total'] === 0) return null;

        $agendadas = (int)$r['agendadas'];
        $limbo     = (int)$r['limbo'];

        return [
            'agendadas'     => $agendadas,
            'nao_agendadas' => $limbo,
            'status'        => $agendadas === 0 ? 'erro' : ($limbo === 0 ? 'concluido' : 'parcial'),
        ];
    }

    public static function porGeracao(int $geracaoId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    d.nome AS disciplina_nome, d.sigla AS disciplina_sigla, d.qtd_aulas, d.qtd_aulas_ead,
                    t.serie_periodo AS turma_nome, t.serie_periodo,
                    p.nome AS professor_nome, p.cor AS professor_cor, p.cor_secundaria AS professor_cor_secundaria,
                    s.nome AS sala_nome,
                    c.id AS curso_id, c.nome AS curso_nome,
                    c.duracao_aula_minutos, c.turno_inicio, c.turno_fim
             FROM horarios h
             JOIN disciplinas d  ON d.id = h.disciplina_id
             JOIN turmas t       ON t.id = h.turma_id
             JOIN professores p  ON p.id = h.professor_id
             LEFT JOIN salas s   ON s.id = h.sala_id
             JOIN cursos c       ON c.id = d.curso_id
             WHERE h.geracao_id = ?
             ORDER BY h.turma_id, h.dia_semana, h.hora_inicio",
            [$geracaoId]
        );
    }

    public static function porTurma(int $turmaId, int $geracaoId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    d.nome AS disciplina_nome, d.sigla AS disciplina_sigla,
                    p.nome AS professor_nome, p.cor AS professor_cor, p.cor_secundaria AS professor_cor_secundaria,
                    s.nome AS sala_nome
             FROM horarios h
             JOIN disciplinas d  ON d.id = h.disciplina_id
             JOIN professores p  ON p.id = h.professor_id
             LEFT JOIN salas s   ON s.id = h.sala_id
             WHERE h.turma_id = ? AND h.geracao_id = ? AND h.dia_semana >= 1
             ORDER BY h.dia_semana, h.hora_inicio",
            [$turmaId, $geracaoId]
        );
    }

    public static function porProfessor(int $professorId, int $geracaoId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    d.nome AS disciplina_nome, d.sigla AS disciplina_sigla,
                    t.serie_periodo AS turma_nome,
                    s.nome AS sala_nome,
                    p.cor AS professor_cor, p.cor_secundaria AS professor_cor_secundaria
             FROM horarios h
             JOIN disciplinas d  ON d.id = h.disciplina_id
             JOIN turmas t       ON t.id = h.turma_id
             LEFT JOIN salas s   ON s.id = h.sala_id
             JOIN professores p  ON p.id = h.professor_id
             WHERE h.professor_id = ? AND h.geracao_id = ? AND h.dia_semana >= 1
             ORDER BY h.dia_semana, h.hora_inicio",
            [$professorId, $geracaoId]
        );
    }

    public static function porSala(int $salaId, int $geracaoId): array
    {
        return Database::fetchAll(
            "SELECT h.*,
                    d.nome AS disciplina_nome, d.sigla AS disciplina_sigla,
                    t.serie_periodo AS turma_nome,
                    p.nome AS professor_nome, p.cor AS professor_cor, p.cor_secundaria AS professor_cor_secundaria
             FROM horarios h
             JOIN disciplinas d  ON d.id = h.disciplina_id
             JOIN turmas t       ON t.id = h.turma_id
             JOIN professores p  ON p.id = h.professor_id
             WHERE h.sala_id = ? AND h.geracao_id = ? AND h.dia_semana >= 1
             ORDER BY h.dia_semana, h.hora_inicio",
            [$salaId, $geracaoId]
        );
    }

    public static function ultimaGeracao(): array|false
    {
        return Database::fetchOne(
            "SELECT * FROM geracoes WHERE status IN ('concluido','parcial') ORDER BY id DESC LIMIT 1"
        );
    }

    public static function todasGeracoes(): array
    {
        return Database::fetchAll(
            "SELECT * FROM geracoes ORDER BY created_at DESC"
        );
    }
}
