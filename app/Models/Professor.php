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

    /**
     * Condição SQL "professor leciona no semestre": ativo e sem "ativo até"
     * anterior ao semestre. `$p` = alias de professores, `$s` = de semestres.
     * Ano*10+período ordena os semestres (2026/2 = 20262 < 20271 = 2027/1).
     */
    public static function condNoSemestre(string $p = 'p', string $s = 'sem'): string
    {
        return "({$p}.ativo = 1 AND ({$p}.ativo_ate_ano IS NULL
                 OR {$p}.ativo_ate_ano * 10 + COALESCE({$p}.ativo_ate_semestre, 2)
                    >= {$s}.ano * 10 + {$s}.semestre))";
    }

    /**
     * Professores selecionáveis num semestre: os que lecionam nele e, além
     * deles, quem JÁ está atribuído ali mesmo fora do período (inativo ou com
     * "ativo até" anterior) — marcado com `fora_periodo = 1`. Sem estes, o
     * dropdown mostraria "Sem professor" e salvar a tela apagaria a atribuição.
     */
    public static function doSemestre(int $semestreId): array
    {
        $cond = self::condNoSemestre('p', 'sem');
        return Database::fetchAll(
            "SELECT p.*, n.nome AS nda_nome,
                    CASE WHEN {$cond} THEN 0 ELSE 1 END AS fora_periodo
             FROM professores p
             JOIN semestres sem ON sem.id = ?
             LEFT JOIN ndas n ON n.id = p.nda_id
             WHERE {$cond}
                OR p.id IN (SELECT professor_id FROM semestre_atribuicoes WHERE semestre_id = ?)
             ORDER BY p.nome",
            [$semestreId, $semestreId]
        );
    }

    /** "2026/2", ou null quando o professor não tem data de saída. */
    public static function rotuloAtivoAte(array $p): ?string
    {
        if (empty($p['ativo_ate_ano'])) return null;
        return (int)$p['ativo_ate_ano'] . '/' . (int)($p['ativo_ate_semestre'] ?: 2);
    }

    public static function allComNda(string $sort = 'nome', string $dir = 'asc'): array
    {
        $map = ['nome' => 'p.nome', 'nda_nome' => 'n.nome', 'usuario_moodle' => 'p.usuario_moodle', 'vinculo' => 'p.vinculo', 'ativo' => 'p.ativo'];
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
