<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\{Horario, Turma, Professor, Sala, Disciplina, Curso};
use App\Services\{ScheduleGenerator, TimeHelper};

/**
 * API REST – todas as respostas são JSON
 */
class ApiController extends BaseController
{
    // GET /api/horarios/turma/{turma_id}/{geracao_id}
    public function horariosTurma(string $turmaId, string $geracaoId): void
    {
        $horarios = Horario::porTurma((int)$turmaId, (int)$geracaoId);
        $this->json(['data' => $horarios]);
    }

    // GET /api/horarios/professor/{professor_id}/{geracao_id}
    public function horariosProfessor(string $profId, string $geracaoId): void
    {
        $horarios = Horario::porProfessor((int)$profId, (int)$geracaoId);
        $this->json(['data' => $horarios]);
    }

    // GET /api/horarios/sala/{sala_id}/{geracao_id}
    public function horariosSala(string $salaId, string $geracaoId): void
    {
        $horarios = Horario::porSala((int)$salaId, (int)$geracaoId);
        $this->json(['data' => $horarios]);
    }

    // GET /api/geracoes
    public function listarGeracoes(): void
    {
        $this->json(['data' => Horario::todasGeracoes()]);
    }

    // GET /api/geracao/{id}
    public function geracao(string $id): void
    {
        $geracao = Database::fetchOne("SELECT * FROM geracoes WHERE id=?", [(int)$id]);
        if (!$geracao) {
            $this->json(['error' => 'Não encontrado'], 404);
            return;
        }
        $total = Database::fetchValue("SELECT COUNT(*) FROM horarios WHERE geracao_id=?", [(int)$id]);
        $geracao['total_horarios'] = $total;
        $this->json(['data' => $geracao]);
    }

    // POST /api/gerar
    public function gerar(): void
    {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $descricao = $body['descricao'] ?? ('API – ' . date('d/m/Y H:i'));
        $pesos     = $body['pesos'] ?? [];

        $geracaoId = (int) Database::fetchValue(
            "INSERT INTO geracoes (descricao, status) VALUES (?, 'processando'); SELECT LAST_INSERT_ID()",
            [$descricao]
        );
        // Reliable last insert
        Database::query("INSERT INTO geracoes (descricao, status) VALUES (?, 'processando')", [$descricao]);
        $geracaoId = (int) Database::lastInsertId();

        try {
            $gerador   = new ScheduleGenerator($geracaoId, $pesos);
            $resultado = $gerador->gerar();
            $this->json(['success' => true, 'data' => $resultado]);
        } catch (\Throwable $e) {
            Database::query(
                "UPDATE geracoes SET status='erro', log=?, finished_at=NOW() WHERE id=?",
                [$e->getMessage(), $geracaoId]
            );
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // GET /api/conflitos/{geracao_id}
    public function conflitos(string $geracaoId): void
    {
        $geracao = Database::fetchOne("SELECT * FROM geracoes WHERE id=?", [(int)$geracaoId]);
        if (!$geracao) {
            $this->json(['error' => 'Não encontrado'], 404);
            return;
        }
        $this->json([
            'geracao_id'       => (int)$geracaoId,
            'status'           => $geracao['status'],
            'falhas'           => $geracao['atividades_falhas'],
            'log'              => $geracao['log'],
        ]);
    }

    // GET /api/disciplinas
    public function listaDisciplinas(): void
    {
        $this->json(['data' => Disciplina::allComRelacoes()]);
    }

    // GET /api/professores
    public function listaProfessores(): void
    {
        $professores = Professor::allAtivos();
        foreach ($professores as &$p) {
            $p['disponibilidade'] = Professor::disponibilidade((int)$p['id']);
        }
        $this->json(['data' => $professores]);
    }

    // GET /api/estatisticas/{geracao_id}
    public function estatisticas(string $geracaoId): void
    {
        $gid = (int)$geracaoId;
        $stats = [
            'total_horarios'   => Database::fetchValue("SELECT COUNT(*) FROM horarios WHERE geracao_id=?", [$gid]),
            'turmas_atendidas' => Database::fetchValue("SELECT COUNT(DISTINCT turma_id) FROM horarios WHERE geracao_id=?", [$gid]),
            'professores'      => Database::fetchValue("SELECT COUNT(DISTINCT professor_id) FROM horarios WHERE geracao_id=?", [$gid]),
            'salas_usadas'     => Database::fetchValue("SELECT COUNT(DISTINCT sala_id) FROM horarios WHERE geracao_id=? AND sala_id IS NOT NULL", [$gid]),
            'por_dia'          => Database::fetchAll(
                "SELECT dia_semana, COUNT(*) AS total FROM horarios WHERE geracao_id=? GROUP BY dia_semana ORDER BY dia_semana",
                [$gid]
            ),
            'carga_por_professor' => Database::fetchAll(
                "SELECT p.nome, COUNT(*) AS encontros,
                        SUM(TIME_TO_SEC(TIMEDIFF(h.hora_fim, h.hora_inicio))/60) AS minutos
                 FROM horarios h
                 JOIN professores p ON p.id = h.professor_id
                 WHERE h.geracao_id = ?
                 GROUP BY h.professor_id ORDER BY minutos DESC",
                [$gid]
            ),
        ];
        $this->json(['data' => $stats]);
    }
}
