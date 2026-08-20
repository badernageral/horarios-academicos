<?php

namespace App\Controllers;

use App\Models\{Professor, Nda, Turno};
use App\Core\Database;

class ProfessoresController extends BaseController
{
    public function index(): void
    {
        [$sort, $dir] = $this->sortParams(['nome', 'usuario_moodle', 'ativo'], 'nome');
        $professores = Professor::allComNda($sort, $dir);
        $flash       = $this->getFlash();

        // Professores agrupados por NDA. A unicidade de cores é verificada
        // DENTRO de cada NDA: a paleta tem 50 pares e o que se compara na grade
        // são professores do mesmo NDA — exigir unicidade global travaria a
        // partir de 50 cadastros.
        $grupos = [];
        foreach ($professores as $p) {
            $ndaId = $p['nda_id'] !== null ? (int)$p['nda_id'] : 0;
            if (!isset($grupos[$ndaId])) {
                $grupos[$ndaId] = [
                    'id'           => $ndaId,
                    'nome'         => $p['nda_nome'] ?? 'Sem NDA',
                    'professores'  => [],
                    'conflitantes' => [],
                ];
            }
            $grupos[$ndaId]['professores'][] = $p;
        }
        uasort($grupos, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));

        foreach ($grupos as &$g) {
            $porPar = [];
            foreach ($g['professores'] as $p) {
                $porPar[$p['cor'] . '|' . $p['cor_secundaria']][$p['id']] = $p['nome'];
            }
            foreach ($g['professores'] as $p) {
                $iguais = $porPar[$p['cor'] . '|' . $p['cor_secundaria']] ?? [];
                unset($iguais[$p['id']]);
                if ($iguais) {
                    $g['conflitantes'][$p['id']] = array_values($iguais);
                }
            }
        }
        unset($g);

        $this->render('professores/index', compact('grupos', 'flash', 'sort', 'dir'));
    }

    public function novo(): void
    {
        $config = require ROOT_PATH . '/config/app.php';
        $ndas   = Nda::allAtivos();

        $turnos = Turno::todos();

        // Professor novo nasce disponível em tudo, como era antes desta tela.
        $gradeDisp = [];
        foreach ([1, 2, 3, 4, 5] as $dia) {
            foreach (array_keys($turnos) as $chave) {
                $gradeDisp[$dia][$chave] = 1;
            }
        }

        $this->render('professores/form', [
            'professor' => null, 'config' => $config, 'ndas' => $ndas,
            'flash' => null, 'gradeDisp' => $gradeDisp, 'turnos' => $turnos,
        ]);
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

        $usuarioMoodle = trim($this->post('usuario_moodle', ''));

        $data = [
            'nome'          => trim($this->post('nome')),
            'matricula'     => $matricula,
            'nda_id'        => (int)$ndaId,
            'usuario_moodle'=> $usuarioMoodle !== '' ? $usuarioMoodle : null,
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

        // Disponibilidade: grade de 3 turnos x 5 dias, 3 estados por retângulo.
        // Só os turnos marcados viram linha; "não pode" é a ausência de linha.
        $grade = $this->post('disp', []);

        $disponibilidades = [];
        foreach ([1, 2, 3, 4, 5] as $dia) {
            foreach (array_keys(Turno::todos()) as $chave) {
                $estado = (int)($grade[$dia][$chave] ?? 0);
                if ($estado !== 1 && $estado !== 2) continue;

                $disponibilidades[] = [
                    'dia_semana' => $dia,
                    'turno'      => $chave,
                    'estado'     => $estado,
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
        $turnos = Turno::todos();
        $this->render('professores/form', [
            'professor' => $professor, 'config' => $config, 'ndas' => $ndas, 'flash' => null,
            'gradeDisp' => Professor::gradeDisponibilidade((int)$id, $turnos), 'turnos' => $turnos,
        ]);
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

    /**
     * Reatribui cores para eliminar pares duplicados DENTRO de um NDA.
     *
     * O escopo é o NDA porque a paleta tem 50 pares: com 100 professores não há
     * como dar cores únicas a todos, e o que se compara visualmente na grade são
     * professores do mesmo NDA.
     *
     * Só mexe em quem está duplicado: a PRIMEIRA ocorrência de cada par (menor
     * id) fica como está, as demais recebem um par ainda livre. Assim quem já
     * identifica um professor pela cor não perde a referência. Cores fora da
     * paleta são preservadas se forem únicas no grupo.
     */
    public function corrigirCores(): void
    {
        $ndaId = $this->post('nda_id');
        if ($ndaId === null || $ndaId === '') {
            $this->flash('danger', 'NDA não informado.');
            $this->redirect('/professores');
            return;
        }
        $ndaId = (int)$ndaId;

        $norm = fn(array $p): string =>
            strtolower(trim((string)$p['cor']) . '|' . trim((string)$p['cor_secundaria']));

        // Pares da paleta indexados pela mesma chave normalizada.
        $livres = [];
        foreach (self::paletaDupla() as $par) {
            $livres[strtolower($par[0] . '|' . $par[1])] = $par;
        }

        // Só o NDA pedido. nda_id = 0 representa os professores sem NDA.
        $profs = $ndaId === 0
            ? Database::fetchAll("SELECT id, nome, cor, cor_secundaria FROM professores WHERE nda_id IS NULL ORDER BY id")
            : Database::fetchAll("SELECT id, nome, cor, cor_secundaria FROM professores WHERE nda_id = ? ORDER BY id", [$ndaId]);

        $ndaNome = $ndaId === 0
            ? 'Sem NDA'
            : (string)(Database::fetchValue("SELECT nome FROM ndas WHERE id = ?", [$ndaId]) ?: 'NDA');

        // Quem chegou primeiro fica com o par; os seguintes entram na fila.
        $dono       = [];
        $reatribuir = [];
        $primarias  = [];                        // cor principal já em uso
        foreach ($profs as $p) {
            $chave = $norm($p);
            if (!isset($dono[$chave])) {
                $dono[$chave] = (int)$p['id'];
                $primarias[strtolower(trim((string)$p['cor']))] = true;
                unset($livres[$chave]);          // par ocupado deixa de estar livre
            } else {
                $reatribuir[] = $p;
            }
        }

        if (empty($reatribuir)) {
            $this->flash('info', "Nenhuma cor repetida em {$ndaNome} — nada a corrigir.");
            $this->redirect('/professores');
            return;
        }

        $corrigidos = 0;
        $semPar     = [];

        Database::beginTransaction();
        try {
            foreach ($reatribuir as $p) {
                // Preferir um par cuja cor PRINCIPAL ainda não esteja em uso: é
                // ela que pinta o bloco na grade, então duas primárias iguais
                // continuariam parecendo o mesmo professor mesmo com o par
                // distinto. Se não houver, aceita qualquer par livre.
                $escolhida = null;
                foreach ($livres as $k => $par) {
                    if (!isset($primarias[strtolower($par[0])])) { $escolhida = $k; break; }
                }
                $escolhida ??= array_key_first($livres);

                if ($escolhida === null) {      // paleta esgotada (mais de 50 professores)
                    $semPar[] = $p['nome'];
                    continue;
                }

                $par = $livres[$escolhida];
                unset($livres[$escolhida]);

                Professor::update((int)$p['id'], ['cor' => $par[0], 'cor_secundaria' => $par[1]]);
                $dono[strtolower($par[0] . '|' . $par[1])] = (int)$p['id'];
                $primarias[strtolower($par[0])] = true;
                $corrigidos++;
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            $this->flash('danger', 'Falha ao corrigir as cores: ' . $e->getMessage());
            $this->redirect('/professores');
            return;
        }

        if ($semPar) {
            $this->flash('warning', sprintf(
                '%s: %d professor(es) receberam cores novas, mas a paleta de 50 pares acabou — %s seguem duplicados.',
                $ndaNome, $corrigidos, implode(', ', $semPar)
            ));
        } else {
            $this->flash('success', $corrigidos === 1
                ? "{$ndaNome}: 1 professor recebeu cores novas. Sem duplicidade no grupo."
                : "{$ndaNome}: {$corrigidos} professores receberam cores novas. Sem duplicidade no grupo.");
        }
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

        // Turnos da grade de disponibilidade (professor importado nasce com tudo verde)
        $turnosImport = Turno::todos();

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

            foreach ([1, 2, 3, 4, 5] as $dia) {
                foreach (array_keys($turnosImport) as $chave) {
                    $disp[] = ['dia_semana' => $dia, 'turno' => $chave, 'estado' => 1];
                }
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
