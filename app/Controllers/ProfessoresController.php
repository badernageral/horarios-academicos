<?php

namespace App\Controllers;

use App\Models\{Professor, Nda};
use App\Core\Database;

class ProfessoresController extends BaseController
{
    public function index(): void
    {
        [$sort, $dir] = $this->sortParams(['nome', 'nda_nome', 'ativo'], 'nome');
        $professores = Professor::allComNda($sort, $dir);
        $flash       = $this->getFlash();

        $cores    = array_column($professores, 'cor');
        $coresSec = array_column($professores, 'cor_secundaria');
        $temCoresRepetidas = count($cores) !== count(array_unique($cores))
                          || count($coresSec) !== count(array_unique($coresSec));

        $this->render('professores/index', compact('professores', 'flash', 'temCoresRepetidas', 'sort', 'dir'));
    }

    public function novo(): void
    {
        $config = require ROOT_PATH . '/config/app.php';
        $ndas   = Nda::allAtivos();
        $this->render('professores/form', ['professor' => null, 'config' => $config, 'ndas' => $ndas, 'flash' => null]);
    }

    public function salvar(): void
    {
        $id = $this->post('id');

        // Gerar matrícula automática se não existir
        $matricula = $id
            ? (Professor::find((int)$id)['matricula'] ?? null)
            : null;
        if (!$matricula) {
            $matricula = 'P' . str_pad((int)Database::fetchValue("SELECT COALESCE(MAX(id),0)+1 FROM professores") , 4, '0', STR_PAD_LEFT);
        }

        $cor = trim($this->post('cor', '#3b82f6'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) $cor = '#3b82f6';

        $corSec = trim($this->post('cor_secundaria', '#f97316'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $corSec)) {
            $hex    = intval(ltrim($cor, '#'), 16);
            $corSec = '#' . str_pad(dechex(0xFFFFFF ^ $hex), 6, '0', STR_PAD_LEFT);
        }

        $ndaId = $this->post('nda_id');
        if (!$ndaId) {
            $this->flash('danger', 'O NDA é obrigatório.');
            $this->redirect($id ? '/professores/' . $id . '/editar' : '/professores/novo');
            return;
        }

        $data = [
            'nome'          => trim($this->post('nome')),
            'matricula'     => $matricula,
            'nda_id'        => (int)$ndaId,
            'ativo'         => $this->post('ativo', 1),
            'cor'           => $cor,
            'cor_secundaria'=> $corSec,
        ];

        if ($id) {
            Professor::update((int)$id, $data);
            $profId = (int)$id;
            $this->flash('success', 'Professor atualizado com sucesso!');
        } else {
            $profId = Professor::create($data);
            $this->flash('success', 'Professor cadastrado com sucesso!');
        }

        // Disponibilidade
        $disponibilidades = [];
        $dias = $this->post('disp_dia', []);
        $inic = $this->post('disp_inicio', []);
        $fins = $this->post('disp_fim', []);
        foreach ($dias as $k => $dia) {
            if (!empty($dia) && !empty($inic[$k]) && !empty($fins[$k])) {
                $disponibilidades[] = [
                    'dia_semana'  => (int)$dia,
                    'hora_inicio' => $inic[$k],
                    'hora_fim'    => $fins[$k],
                ];
            }
        }
        Professor::salvarDisponibilidade($profId, $disponibilidades);

        $this->redirect('/professores');
    }

    public function editar(string $id): void
    {
        $professor = Professor::find((int)$id);
        if (!$professor) {
            $this->redirect('/professores');
        }
        $professor['disponibilidade'] = Professor::disponibilidade((int)$id);
        $config = require ROOT_PATH . '/config/app.php';
        $ndas   = Nda::allAtivos();
        $this->render('professores/form', ['professor' => $professor, 'config' => $config, 'ndas' => $ndas, 'flash' => null]);
    }

    public function deletar(): void
    {
        $id = (int)$this->post('id');
        if ($id) {
            Professor::delete($id);
            $this->flash('success', 'Professor removido.');
        }
        $this->redirect('/professores');
    }

    public function corrigirCores(): void
    {
        $pares = self::paletaDupla();
        $n     = count($pares);

        $rows = Database::fetchAll("SELECT id FROM professores ORDER BY id");

        $corrigidos = 0;
        foreach ($rows as $idx => $row) {
            [$cor, $corSec] = $pares[$idx % $n];
            $atual = Database::fetchOne(
                "SELECT cor, cor_secundaria FROM professores WHERE id = ?",
                [$row['id']]
            );
            if ($atual['cor'] !== $cor || $atual['cor_secundaria'] !== $corSec) {
                Database::query(
                    "UPDATE professores SET cor = ?, cor_secundaria = ? WHERE id = ?",
                    [$cor, $corSec, $row['id']]
                );
                $corrigidos++;
            }
        }

        $this->flash('success', "Cores redistribuídas ({$corrigidos} alterada(s)).");
        $this->redirect('/professores');
    }

    private static function paletaDupla(): array
    {
        // 50 cores agrupadas em 10 famílias de matiz × 5 tons.
        // Índice i recebe (cores[i], cores[i+25]): matizes opostos no círculo cromático.
        $cores = [
            '#dc2626','#ef4444','#f87171','#b91c1c','#7f1d1d', // Vermelhos
            '#ea580c','#f97316','#fb923c','#c2410c','#9a3412', // Laranjas
            '#d97706','#f59e0b','#fbbf24','#b45309','#78350f', // Âmbares
            '#65a30d','#84cc16','#a3e635','#4d7c0f','#365314', // Limas
            '#16a34a','#22c55e','#4ade80','#15803d','#14532d', // Verdes
            '#0d9488','#14b8a6','#2dd4bf','#0f766e','#134e4a', // Teais
            '#0891b2','#06b6d4','#22d3ee','#0e7490','#164e63', // Cianos
            '#2563eb','#3b82f6','#60a5fa','#1d4ed8','#1e3a8a', // Azuis
            '#6366f1','#818cf8','#a5b4fc','#4338ca','#312e81', // Índigos
            '#9333ea','#a855f7','#c084fc','#7e22ce','#581c87', // Violetas
        ];
        $n    = count($cores);
        $meio = intdiv($n, 2);
        $pares = [];
        for ($i = 0; $i < $n; $i++) {
            $pares[] = [$cores[$i], $cores[($i + $meio) % $n]];
        }
        return $pares;
    }

    public function verImportar(): void
    {
        $this->render('professores/importar', ['flash' => null]);
    }

    public function importar(): void
    {
        $texto  = $this->post('nomes', '');
        $linhas = array_filter(array_map('trim', explode("\n", $texto)));

        $pares = self::paletaDupla();

        // Cache de NDAs por nome (case-insensitive)
        $ndaMap = [];
        foreach (Database::fetchAll("SELECT id, nome FROM ndas WHERE ativo = 1") as $n) {
            $ndaMap[strtolower(trim($n['nome']))] = (int)$n['id'];
        }

        $criados  = 0;
        $pulados  = 0;

        foreach ($linhas as $linha) {
            $linha = trim($linha, " \r\n");
            if ($linha === '') continue;

            $partes  = explode('-', $linha, 2);
            $nome    = trim($partes[0], " \t");
            $ndaNome = isset($partes[1]) ? trim($partes[1], " \t") : '';

            if ($nome === '') continue;

            $ndaId = $ndaMap[strtolower($ndaNome)] ?? null;
            if (!$ndaId) {
                $pulados++;
                continue;
            }

            $proxId    = (int)Database::fetchValue("SELECT COALESCE(MAX(id),0)+1 FROM professores");
            $matricula = 'P' . str_pad($proxId, 4, '0', STR_PAD_LEFT);

            [$cor, $corSec] = $pares[$criados % count($pares)];
            $profId = Professor::create([
                'nome'          => $nome,
                'matricula'     => $matricula,
                'nda_id'        => $ndaId,
                'ativo'         => 1,
                'cor'           => $cor,
                'cor_secundaria'=> $corSec,
            ]);

            for ($dia = 1; $dia <= 5; $dia++) {
                $disp[] = ['dia_semana' => $dia, 'hora_inicio' => '07:00', 'hora_fim' => '23:00'];
            }
            Professor::salvarDisponibilidade($profId, $disp ?? []);
            $disp = [];

            $criados++;
        }

        $msg = "{$criados} professor(es) cadastrado(s).";
        if ($pulados) $msg .= " {$pulados} linha(s) pulada(s) por NDA não encontrado.";
        $this->flash($criados > 0 ? 'success' : 'warning', $msg);
        $this->redirect('/professores');
    }
}
