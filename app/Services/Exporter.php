<?php

namespace App\Services;

use App\Models\Horario;
use App\Core\Database;

class Exporter
{
    private array $diasNomes = [
        1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta',
        4 => 'Quinta',  5 => 'Sexta', 6 => 'Sábado',
    ];

    // ──────────────────────────────────────────────────────────────
    // CSV
    // ──────────────────────────────────────────────────────────────
    public function exportarCSV(array $horarios, string $titulo = 'Horário'): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitizeFilename($titulo) . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 para Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, ['Dia', 'Hora Início', 'Hora Fim', 'Duração (min)', 'Disciplina', 'Turma', 'Professor', 'Sala'], ';');

        foreach ($horarios as $h) {
            $duracao = TimeHelper::duration($h['hora_inicio'], $h['hora_fim']);
            fputcsv($out, [
                $this->diasNomes[$h['dia_semana']] ?? $h['dia_semana'],
                substr($h['hora_inicio'], 0, 5),
                substr($h['hora_fim'], 0, 5),
                $duracao,
                $h['disciplina_nome'],
                $h['turma_nome'] ?? '',
                $h['professor_nome'],
                $h['sala_nome'] ?? 'Sem sala',
            ], ';');
        }

        fclose($out);
    }

    // ──────────────────────────────────────────────────────────────
    // Excel (HTML table que o Excel abre)
    // ──────────────────────────────────────────────────────────────
    public function exportarExcel(array $horarios, string $titulo = 'Horário'): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->sanitizeFilename($titulo) . '.xls"');
        header('Pragma: no-cache');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
               xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head><meta charset="UTF-8">
        <style>
        td { border: 1px solid #999; padding: 4px 8px; }
        th { background: #1d4ed8; color: white; border: 1px solid #1d4ed8; padding: 6px 10px; font-weight: bold; }
        h2 { font-family: Arial; }
        </style></head><body>';

        echo "<h2>{$titulo}</h2>";
        echo '<table><tr>
            <th>Dia</th><th>Hora Início</th><th>Hora Fim</th><th>Duração</th>
            <th>Disciplina</th><th>Turma</th><th>Professor</th><th>Sala</th>
        </tr>';

        foreach ($horarios as $h) {
            $duracao = TimeHelper::duration($h['hora_inicio'], $h['hora_fim']);
            echo '<tr>';
            echo '<td>' . htmlspecialchars($this->diasNomes[$h['dia_semana']] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars(substr($h['hora_inicio'], 0, 5)) . '</td>';
            echo '<td>' . htmlspecialchars(substr($h['hora_fim'], 0, 5)) . '</td>';
            echo '<td>' . $duracao . 'min</td>';
            echo '<td>' . htmlspecialchars($h['disciplina_nome']) . '</td>';
            echo '<td>' . htmlspecialchars($h['turma_nome'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($h['professor_nome']) . '</td>';
            echo '<td>' . htmlspecialchars($h['sala_nome'] ?? 'Sem sala') . '</td>';
            echo '</tr>';
        }

        echo '</table></body></html>';
    }

    // ──────────────────────────────────────────────────────────────
    // HTML para impressão / PDF
    // ──────────────────────────────────────────────────────────────
    public function gerarHTML(array $horarios, string $titulo, string $tipo = 'turma'): string
    {
        $agrupado = [];

        if ($tipo === 'turma') {
            foreach ($horarios as $h) {
                $agrupado[$h['turma_nome'] ?? 'Sem turma'][$h['dia_semana']][] = $h;
            }
        } elseif ($tipo === 'professor') {
            foreach ($horarios as $h) {
                $agrupado[$h['professor_nome']][$h['dia_semana']][] = $h;
            }
        } elseif ($tipo === 'sala') {
            foreach ($horarios as $h) {
                $agrupado[$h['sala_nome'] ?? 'Sem sala'][$h['dia_semana']][] = $h;
            }
        }

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
        <title>' . htmlspecialchars($titulo) . '</title>
        <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 20px; }
        h2 { font-size: 13px; margin-top: 20px; border-bottom: 2px solid #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #1d4ed8; color: white; padding: 6px; text-align: center; font-size: 11px; }
        td { border: 1px solid #ccc; padding: 5px; vertical-align: top; font-size: 10px; min-height: 30px; }
        .aula { background: #dbeafe; border-radius: 3px; padding: 3px; margin-bottom: 3px; }
        @media print {
            @page { size: landscape; margin: 1cm; }
            h2 { page-break-before: auto; }
        }
        </style></head><body>';
        $html .= '<h1>' . htmlspecialchars($titulo) . '</h1>';

        $dias = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];

        foreach ($agrupado as $nome => $porDia) {
            $diasPresentes = array_keys($porDia);
            sort($diasPresentes);

            $html .= '<h2>' . htmlspecialchars($nome) . '</h2>';
            $html .= '<table><tr><th>Dia</th><th>Horário</th><th>Disciplina</th><th>Professor</th><th>Sala</th></tr>';

            foreach ($diasPresentes as $dia) {
                usort($porDia[$dia], fn($a, $b) => strcmp($a['hora_inicio'], $b['hora_inicio']));
                foreach ($porDia[$dia] as $i => $h) {
                    $html .= '<tr>';
                    if ($i === 0) {
                        $html .= '<td rowspan="' . count($porDia[$dia]) . '" style="text-align:center;font-weight:bold;">'
                            . ($dias[$dia] ?? $dia) . '</td>';
                    }
                    $html .= '<td>' . substr($h['hora_inicio'], 0, 5) . ' – ' . substr($h['hora_fim'], 0, 5) . '</td>';
                    $html .= '<td>' . htmlspecialchars($h['disciplina_nome']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($h['professor_nome']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($h['sala_nome'] ?? '—') . '</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</table>';
        }

        $html .= '</body></html>';
        return $html;
    }

    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    }
}
