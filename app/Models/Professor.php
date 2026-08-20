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
        $map = ['nome' => 'p.nome', 'nda_nome' => 'n.nome', 'usuario_moodle' => 'p.usuario_moodle', 'ativo' => 'p.ativo'];
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
             ORDER BY dia_semana, turno",
            [$professorId]
        );
    }

    /**
     * Disponibilidade no formato da grade do formulário: [dia][turno] = estado.
     * 0 = não pode (sem linha no banco), 1 = pode, 2 = só se não houver verde.
     */
    public static function gradeDisponibilidade(int $professorId, array $turnos): array
    {
        $salvo = [];
        foreach (self::disponibilidade($professorId) as $l) {
            $salvo[(int)$l['dia_semana']][$l['turno']] = (int)$l['estado'] === 2 ? 2 : 1;
        }

        $grade = [];
        foreach ([1, 2, 3, 4, 5] as $dia) {
            foreach (array_keys($turnos) as $chave) {
                $grade[$dia][$chave] = $salvo[$dia][$chave] ?? 0;
            }
        }
        return $grade;
    }

    /**
     * Substitui a disponibilidade do professor.
     * Cada item: ['dia_semana' => int, 'turno' => string, 'estado' => 1|2].
     * Turnos "não pode" simplesmente não são enviados.
     */
    public static function salvarDisponibilidade(int $professorId, array $disponibilidades): void
    {
        Database::query("DELETE FROM disponibilidade_professor WHERE professor_id = ?", [$professorId]);

        foreach ($disponibilidades as $d) {
            $turno = trim((string)($d['turno'] ?? ''));
            if ($turno === '') continue;

            $estado = (int)($d['estado'] ?? 1) === 2 ? 2 : 1;
            Database::query(
                "INSERT OR IGNORE INTO disponibilidade_professor
                 (professor_id, dia_semana, turno, estado)
                 VALUES (?, ?, ?, ?)",
                [$professorId, (int)$d['dia_semana'], $turno, $estado]
            );
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
                    "INSERT OR IGNORE INTO professor_qualificacao (professor_id, disciplina_nome) VALUES (?, ?)",
                    [$professorId, $nome]
                );
            }
        }
    }

    public static function cargaHorariaSemanal(int $professorId, int $geracaoId): int
    {
        $result = Database::fetchOne(
            "SELECT COALESCE(SUM(
                        (substr(hora_fim,1,2)*60 + substr(hora_fim,4,2))
                      - (substr(hora_inicio,1,2)*60 + substr(hora_inicio,4,2))
                    ), 0) AS total
             FROM horarios
             WHERE professor_id = ? AND geracao_id = ?",
            [$professorId, $geracaoId]
        );
        return (int)($result['total'] ?? 0);
    }
}
