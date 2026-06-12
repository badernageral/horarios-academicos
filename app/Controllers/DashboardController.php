<?php

namespace App\Controllers;

use App\Core\Database;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $stats = [
            'cursos'      => Database::fetchValue("SELECT COUNT(*) FROM cursos WHERE ativo=1"),
            'professores' => Database::fetchValue("SELECT COUNT(*) FROM professores WHERE ativo=1"),
            'turmas'      => Database::fetchValue("SELECT COUNT(*) FROM turmas WHERE ativo=1"),
            'disciplinas' => Database::fetchValue("SELECT COUNT(*) FROM disciplinas WHERE ativo=1"),
            'salas'       => Database::fetchValue("SELECT COUNT(*) FROM salas WHERE ativo=1"),
            'semestres'   => Database::fetchValue("SELECT COUNT(*) FROM semestres"),
        ];

        $semestres = Database::fetchAll(
            "SELECT s.id, s.semestre, s.ano, s.created_at,
                    g.id AS geracao_id, g.status AS geracao_status,
                    g.atividades_agendadas, g.atividades_falhas, g.created_at AS gerado_em
             FROM semestres s
             LEFT JOIN geracoes g ON g.semestre_id = s.id
             ORDER BY s.ano DESC, s.semestre DESC
             LIMIT 6"
        );

        $this->render('dashboard/index', compact('stats', 'semestres'));
    }
}
