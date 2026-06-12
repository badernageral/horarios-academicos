<?php

namespace App\Models;

use App\Core\Database;

class Professor extends BaseModel
{
    protected static string $table = 'professores';

    public static function allAtivos(): array
    {
        return Database::fetchAll(
            "SELECT p.*, n.nome AS nda_nome
             FROM professores p
             LEFT JOIN ndas n ON n.id = p.nda_id
             WHERE p.ativo = 1 ORDER BY p.nome"
        );
    }

    public static function allComNda(string $sort = 'nome', string $dir = 'asc'): array
    {
        $map = ['nome' => 'p.nome', 'nda_nome' => 'n.nome', 'ativo' => 'p.ativo'];
        $col = $map[$sort] ?? 'p.nome';
        $dir = $dir === 'desc' ? 'DESC' : 'ASC';
        return Database::fetchAll(
            "SELECT p.*, n.nome AS nda_nome
             FROM professores p
             LEFT JOIN ndas n ON n.id = p.nda_id
             ORDER BY {$col} {$dir}"
        );
    }

    public static function disponibilidade(int $professorId): array
    {
        return Database::fetchAll(
            "SELECT * FROM disponibilidade_professor
             WHERE professor_id = ?
             ORDER BY dia_semana, hora_inicio",
            [$professorId]
        );
    }

    public static function salvarDisponibilidade(int $professorId, array $disponibilidades): void
    {
        Database::query("DELETE FROM disponibilidade_professor WHERE professor_id = ?", [$professorId]);

        foreach ($disponibilidades as $d) {
            if (!empty($d['hora_inicio']) && !empty($d['hora_fim'])) {
                Database::query(
                    "INSERT INTO disponibilidade_professor (professor_id, dia_semana, hora_inicio, hora_fim)
                     VALUES (?, ?, ?, ?)",
                    [$professorId, $d['dia_semana'], $d['hora_inicio'], $d['hora_fim']]
                );
            }
        }
    }

    public static function qualificacoes(int $professorId): array
    {
        return Database::fetchAll(
            "SELECT disciplina_nome FROM professor_qualificacao WHERE professor_id = ?",
            [$professorId]
        );
    }

    public static function salvarQualificacoes(int $professorId, array $disciplinas): void
    {
        Database::query("DELETE FROM professor_qualificacao WHERE professor_id = ?", [$professorId]);

        foreach ($disciplinas as $nome) {
            $nome = trim($nome);
            if ($nome !== '') {
                Database::query(
                    "INSERT IGNORE INTO professor_qualificacao (professor_id, disciplina_nome) VALUES (?, ?)",
                    [$professorId, $nome]
                );
            }
        }
    }

    public static function cargaHorariaSemanal(int $professorId, int $geracaoId): int
    {
        $result = Database::fetchOne(
            "SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(hora_fim, hora_inicio))/60), 0) AS total
             FROM horarios
             WHERE professor_id = ? AND geracao_id = ?",
            [$professorId, $geracaoId]
        );
        return (int)($result['total'] ?? 0);
    }
}
