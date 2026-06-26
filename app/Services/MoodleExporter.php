<?php

namespace App\Services;

use App\Core\Database;

/**
 * Gera CSVs no formato de importação em massa do Moodle:
 *  - Disciplinas → "Upload courses"  (shortname,fullname,category,startdate,enddate)
 *  - Professores → "Upload users"    (username,course1,role1,course2,role2,...)
 *
 * O shortname é a chave que liga os dois arquivos e segue o padrão do campus:
 *   {ano}{semestre}{iniciais_curso}{periodo}{iniciais_disciplina}  (sem separadores)
 *   ex.: Agronomia / 4º período / Lógica de Programação / 2026/2 → 20262A4LP
 */
class MoodleExporter
{
    private const ROLE_PROFESSOR = 'editingteacher';

    // Conectivos ignorados ao montar as iniciais de cursos e disciplinas.
    private const CONECTIVOS = [
        'de','do','da','dos','das','e','em','no','na','nos','nas',
        'a','o','as','os','para','com','por',
    ];

    // ──────────────────────────────────────────────────────────────
    // Iniciais / shortname
    // ──────────────────────────────────────────────────────────────

    /**
     * Primeira letra de cada palavra, maiúscula e sem acento, ignorando conectivos.
     * Algarismos romanos de nível/série (I, II, III, IV, V, … até XXXIX) viram o
     * número arábico em vez da letra — assim "Física II" → "F2" e não "FI".
     */
    public static function iniciais(string $nome): string
    {
        $palavras = preg_split('/\s+/u', trim($nome)) ?: [];
        $out = '';
        foreach ($palavras as $w) {
            if ($w === '') continue;
            if (in_array(strtolower($w), self::CONECTIVOS, true)) continue;
            $romano = self::romanoParaInteiro($w);
            if ($romano !== null) {
                $out .= $romano;
            } elseif (preg_match('/^./u', $w, $m)) {
                $out .= strtoupper(self::semAcento($m[0]));
            }
        }
        return $out;
    }

    /**
     * Converte um algarismo romano de nível em inteiro, ou null se a palavra não for um.
     * Restrito a I/V/X (1..39) de propósito: evita interpretar letras de turma como
     * "C" (=100), "L" (=50), "D" (=500) ou "M" (=1000) como romano.
     */
    private static function romanoParaInteiro(string $w): ?int
    {
        $w = strtoupper($w);
        if ($w === '' || !preg_match('/^(X{0,3})(IX|IV|V?I{0,3})$/', $w)) return null;
        $val   = ['I' => 1, 'V' => 5, 'X' => 10];
        $total = 0; $prev = 0;
        for ($i = strlen($w) - 1; $i >= 0; $i--) {
            $v      = $val[$w[$i]];
            $total += $v < $prev ? -$v : $v;
            $prev   = $v;
        }
        return $total;
    }

    /** Extrai o número do período/série/módulo (ex.: "4º período" → "4"). */
    public static function periodo(string $seriePeriodo): string
    {
        return preg_match('/\d+/', $seriePeriodo, $m) ? $m[0] : '';
    }

    private static function shortnameBase(int $ano, int $semestre, string $curso, string $serie, string $disciplina): string
    {
        return $ano . $semestre
            . self::iniciais($curso)
            . self::periodo($serie)
            . self::iniciais($disciplina);
    }

    /**
     * Mapa disciplina_id => shortname para um semestre, com colisões resolvidas
     * de forma determinística (sufixo numérico nas repetições, por ordem de id).
     * Usado por AMBOS os CSVs para garantir que os shortnames batam.
     */
    public static function shortnamesPorDisciplina(int $semestreId): array
    {
        $sem  = Database::fetchOne("SELECT semestre, ano FROM semestres WHERE id = ?", [$semestreId]);
        if (!$sem) return [];
        $ano      = (int)$sem['ano'];
        $semestre = (int)$sem['semestre'];

        $rows = self::disciplinasDoSemestre($semestreId);

        // Agrupa por shortname base para detectar colisões.
        $porBase = [];
        foreach ($rows as $r) {
            $base = self::shortnameBase($ano, $semestre, $r['curso_nome'], $r['serie_periodo'], $r['disciplina_nome']);
            $porBase[$base][] = (int)$r['disciplina_id'];
        }

        $map = [];
        foreach ($porBase as $base => $ids) {
            sort($ids); // determinístico
            $i = 0;
            foreach ($ids as $id) {
                $i++;
                $map[$id] = $i === 1 ? $base : $base . $i; // 2ª+ ocorrência recebe sufixo
            }
        }
        return $map;
    }

    // ──────────────────────────────────────────────────────────────
    // Dados
    // ──────────────────────────────────────────────────────────────

    /**
     * Disciplinas ativas ofertadas no semestre (uma por turma).
     * Disciplinas anuais (semestre_oferta = 3) só entram na exportação do 1º semestre —
     * no 2º semestre o curso já foi criado no Moodle no início do ano.
     */
    public static function disciplinasDoSemestre(int $semestreId): array
    {
        return Database::fetchAll(
            "SELECT d.id AS disciplina_id, d.nome AS disciplina_nome,
                    c.nome AS curso_nome, t.id AS turma_id, t.serie_periodo
             FROM disciplinas d
             JOIN cursos c       ON c.id = d.curso_id
             JOIN turmas t       ON t.id = d.turma_id
             JOIN semestres sem  ON sem.id = ?
             WHERE d.ativo = 1
               AND (d.semestre_oferta & sem.semestre) > 0
               AND NOT (d.semestre_oferta = 3 AND sem.semestre = 2)
             ORDER BY c.nome, t.serie_periodo, d.nome",
            [$semestreId]
        );
    }

    /** Atribuições do semestre com dados para montar o shortname (uma linha por professor×disciplina). */
    public static function atribuicoesDoSemestre(int $semestreId): array
    {
        return Database::fetchAll(
            "SELECT DISTINCT p.id AS professor_id, p.nome AS professor_nome, p.usuario_moodle,
                    d.id AS disciplina_id
             FROM semestre_atribuicoes sa
             JOIN professores p  ON p.id = sa.professor_id
             JOIN disciplinas d  ON d.id = sa.disciplina_id
             WHERE sa.semestre_id = ?
             ORDER BY p.nome",
            [$semestreId]
        );
    }

    // ──────────────────────────────────────────────────────────────
    // CSV de disciplinas (Upload courses)
    // ──────────────────────────────────────────────────────────────

    /**
     * @param array  $categorias  [turma_id => category_id]
     * @param string $startdate   formato YY-MM-DD (ou vazio)
     * @param string $enddate     formato YY-MM-DD (ou vazio)
     */
    public function disciplinasCSV(int $semestreId, array $categorias, string $startdate, string $enddate, string $arquivo): void
    {
        $map  = self::shortnamesPorDisciplina($semestreId);
        $rows = self::disciplinasDoSemestre($semestreId);

        $this->headers($arquivo);
        $out = fopen('php://output', 'w');
        fputcsv($out, ['shortname', 'fullname', 'category', 'startdate', 'enddate'], ',', '"', '\\');

        foreach ($rows as $r) {
            $id       = (int)$r['disciplina_id'];
            $turmaId  = (int)$r['turma_id'];
            $category = trim((string)($categorias[$turmaId] ?? ''));
            fputcsv($out, [
                $map[$id] ?? '',
                $r['disciplina_nome'],
                $category,
                $startdate,
                $enddate,
            ], ',', '"', '\\');
        }
        fclose($out);
    }

    // ──────────────────────────────────────────────────────────────
    // CSV de professores (Upload users com atribuição de papel)
    // ──────────────────────────────────────────────────────────────

    public function professoresCSV(int $semestreId, string $arquivo): void
    {
        $map = self::shortnamesPorDisciplina($semestreId);

        // Agrupa cursos (shortnames) por professor; só quem tem usuário Moodle.
        $porProf = [];
        foreach (self::atribuicoesDoSemestre($semestreId) as $a) {
            $usuario = trim((string)($a['usuario_moodle'] ?? ''));
            if ($usuario === '') continue;
            $short = $map[(int)$a['disciplina_id']] ?? null;
            if (!$short) continue;
            $porProf[$usuario][$short] = true; // dedupe
        }

        $maxCursos = 0;
        foreach ($porProf as $cursos) {
            $maxCursos = max($maxCursos, count($cursos));
        }
        $maxCursos = max(1, $maxCursos);

        $header = ['username'];
        for ($i = 1; $i <= $maxCursos; $i++) {
            $header[] = "course{$i}";
            $header[] = "role{$i}";
        }

        $this->headers($arquivo);
        $out = fopen('php://output', 'w');
        fputcsv($out, $header, ',', '"', '\\');

        foreach ($porProf as $usuario => $cursos) {
            $linha = [$usuario];
            foreach (array_keys($cursos) as $short) {
                $linha[] = $short;
                $linha[] = self::ROLE_PROFESSOR;
            }
            // Preenche colunas restantes para manter o número de campos uniforme.
            while (count($linha) < 1 + $maxCursos * 2) {
                $linha[] = '';
            }
            fputcsv($out, $linha, ',', '"', '\\');
        }
        fclose($out);
    }

    // ──────────────────────────────────────────────────────────────
    // Pré-visualização / validação para a tela de export
    // ──────────────────────────────────────────────────────────────

    /** Disciplinas com shortname calculado + colisões e professores sem usuário Moodle. */
    public static function diagnostico(int $semestreId): array
    {
        $map  = self::shortnamesPorDisciplina($semestreId);
        $rows = self::disciplinasDoSemestre($semestreId);

        // Colisões = mesmo shortname base gerado por mais de uma disciplina.
        $colisoes = [];
        $bases    = [];
        $sem   = Database::fetchOne("SELECT semestre, ano FROM semestres WHERE id = ?", [$semestreId]);
        $ano   = (int)($sem['ano'] ?? 0);
        $s     = (int)($sem['semestre'] ?? 0);
        foreach ($rows as $r) {
            $base = self::shortnameBase($ano, $s, $r['curso_nome'], $r['serie_periodo'], $r['disciplina_nome']);
            $bases[$base][] = $r['disciplina_nome'] . ' (' . $r['serie_periodo'] . ')';
        }
        foreach ($bases as $base => $discs) {
            if (count($discs) > 1) $colisoes[$base] = $discs;
        }

        // Professores atribuídos sem usuário Moodle.
        $semUsuario = Database::fetchAll(
            "SELECT DISTINCT p.nome
             FROM semestre_atribuicoes sa
             JOIN professores p ON p.id = sa.professor_id
             WHERE sa.semestre_id = ?
               AND (p.usuario_moodle IS NULL OR p.usuario_moodle = '')
             ORDER BY p.nome",
            [$semestreId]
        );

        return [
            'map'         => $map,
            'rows'        => $rows,
            'colisoes'    => $colisoes,
            'sem_usuario' => array_column($semUsuario, 'nome'),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function headers(string $arquivo): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $arquivo) . '.csv"');
        header('Pragma: no-cache');
        // Sem BOM: o Moodle espera UTF-8 puro e o BOM corromperia o 1º cabeçalho.
    }

    private static function semAcento(string $s): string
    {
        return strtr($s, [
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','á'=>'A','à'=>'A','â'=>'A','ã'=>'A','ä'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','é'=>'E','è'=>'E','ê'=>'E','ë'=>'E',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','í'=>'I','ì'=>'I','î'=>'I','ï'=>'I',
            'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','ó'=>'O','ò'=>'O','ô'=>'O','õ'=>'O','ö'=>'O',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ú'=>'U','ù'=>'U','û'=>'U','ü'=>'U',
            'Ç'=>'C','ç'=>'C','Ñ'=>'N','ñ'=>'N',
        ]);
    }
}
