<?php

namespace App\Services;

require_once ROOT_PATH . '/lib/fpdf/fpdf.php';

/**
 * Gera o PDF da grade (uma turma por página) com FPDF.
 *
 * Reproduz o layout da tela: mesma malha de slots (via GradeLayout), blocos
 * com a cor do professor em ~35% e faixa inferior com o nome em branco.
 *
 * Sem dependências externas: FPDF é PHP puro (vendorizado em lib/fpdf) e a
 * conversão de acentos é feita aqui, sem mbstring nem iconv, porque o PHP
 * embutido do app desktop não carrega essas extensões.
 */
class PdfExporter
{
    private const DIAS = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta'];

    private \FPDF $pdf;
    private float $margem = 8.0;

    /** Larguras (mm): coluna de hora + 5 dias, calculadas por página */
    private float $colHora;
    private float $colDia;

    public function __construct(private string $orientacao = 'landscape')
    {
        $this->pdf = new \FPDF($this->orientacao === 'portrait' ? 'P' : 'L', 'mm', 'A4');
        $this->pdf->SetMargins($this->margem, $this->margem, $this->margem);
        $this->pdf->SetAutoPageBreak(false); // uma turma por página, sem quebra
        $util = $this->pdf->GetPageWidth() - 2 * $this->margem;
        $this->colHora = $this->orientacao === 'portrait' ? 20.0 : 24.0;
        $this->colDia  = ($util - $this->colHora) / 5;
    }

    /**
     * @param array $grade  Saída de GradeLayout::montar(), já filtrada pelas turmas
     */
    public function gerar(array $grade, string $nomeArquivo): void
    {
        foreach ($grade as $turma) {
            $this->pdf->AddPage();
            $this->desenharTurma($turma);
        }
        if (empty($grade)) {
            $this->pdf->AddPage();
            $this->pdf->SetFont('Helvetica', '', 12);
            $this->pdf->Cell(0, 10, $this->txt('Nenhuma turma selecionada.'), 0, 1);
        }

        $this->pdf->Output('D', $nomeArquivo . '.pdf');
    }

    private function desenharTurma(array $turma): void
    {
        $pdf = $this->pdf;
        $alturaUtil = $pdf->GetPageHeight() - 2 * $this->margem;

        // Cabeçalho da turma (faixa escura, como na tela)
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(241, 245, 249);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell($this->colHora + 5 * $this->colDia, 7,
            $this->txt($turma['curso_nome'] . ' - ' . $turma['turma_nome']), 1, 1, 'L', true);

        // Cabeçalho dos dias
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Cell($this->colHora, 6, $this->txt('Hora'), 1, 0, 'L', true);
        foreach (self::DIAS as $dia) {
            $pdf->Cell($this->colDia, 6, $this->txt($dia), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Altura de cada linha: divide o espaço restante entre os slots
        $topo     = $pdf->GetY();
        $restante = $alturaUtil - ($topo - $this->margem);
        $alturaSlot = $this->alturaSlot($turma, $restante);

        $slots = $turma['slots'];
        foreach ($slots as $idx => $slot) {
            $y = $pdf->GetY();
            $h = $slot['type'] === 'intervalo' ? min($alturaSlot, 5.0) : $alturaSlot;

            // Coluna de hora
            $pdf->SetXY($this->margem, $y);
            $pdf->SetFillColor(248, 250, 252);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetFont('Helvetica', '', 6.5);
            $rotulo = TimeHelper::fromMinutes($slot['min']) . '-' . TimeHelper::fromMinutes($slot['fim']);
            $pdf->Cell($this->colHora, $h, $this->txt($rotulo), 1, 0, 'C', true);

            if ($slot['type'] === 'intervalo') {
                $pdf->SetFillColor(226, 232, 240);
                $pdf->SetFont('Helvetica', 'I', 6.5);
                $pdf->Cell(5 * $this->colDia, $h, $this->txt('Intervalo'), 1, 1, 'C', true);
                continue;
            }

            // Cinco colunas de dias
            $x = $this->margem + $this->colHora;
            for ($dia = 1; $dia <= 5; $dia++) {
                $bloco = $turma['grid'][$dia][$idx] ?? null;
                $pular = $turma['skip'][$dia][$idx] ?? false;

                if ($pular) { $x += $this->colDia; continue; } // coberto por rowspan acima

                if ($bloco === null) {
                    $pdf->SetXY($x, $y);
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->Cell($this->colDia, $h, '', 1, 0, 'C', true);
                    $x += $this->colDia;
                    continue;
                }

                $this->desenharBloco($bloco, $x, $y, $this->colDia, $h * (int)$bloco['rowspan']);
                $x += $this->colDia;
            }
            $pdf->SetXY($this->margem, $y + $h);
        }
    }

    /**
     * Altura por slot que faz a turma inteira caber na página.
     *
     * A divisão nunca considera menos que SLOTS_REFERENCIA linhas: sem isso uma
     * turma com poucos horários espalharia a página inteira entre suas poucas
     * linhas, gerando blocos enormes. Turmas cheias (>= referência) continuam
     * ocupando a folha toda, e o limite acompanha a orientação (em retrato há
     * mais altura disponível, então as linhas ficam proporcionalmente maiores).
     */
    private const SLOTS_REFERENCIA = 12;

    private function alturaSlot(array $turma, float $restante): float
    {
        $aulas      = 0;
        $intervalos = 0;
        foreach ($turma['slots'] as $s) {
            $s['type'] === 'intervalo' ? $intervalos++ : $aulas++;
        }
        if ($aulas === 0) return 6.0;
        // Intervalos ocupam no máximo 5mm; o resto se divide entre as aulas
        $sobra   = $restante - min(5.0, $restante / max(1, count($turma['slots']))) * $intervalos;
        $divisor = max($aulas, self::SLOTS_REFERENCIA);
        return max(4.0, $sobra / $divisor);
    }

    private function desenharBloco(array $bloco, float $x, float $y, float $w, float $h): void
    {
        $pdf = $this->pdf;
        [$r, $g, $b] = $this->hexRgb($bloco['professor_cor'] ?? '#94a3b8');
        // Fundo = cor do professor a ~35% sobre branco (mesma ideia do sufixo alpha 59 na tela)
        $pdf->SetFillColor(
            (int)round(255 - (255 - $r) * 0.35),
            (int)round(255 - (255 - $g) * 0.35),
            (int)round(255 - (255 - $b) * 0.35)
        );
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, '', 1, 0, 'C', true);

        $faixa = $bloco['professor_nome'] ? 3.2 : 1.2;

        // Nome da disciplina (quebra em linhas dentro do bloco)
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', 'B', 6.5);
        $pdf->SetXY($x + 0.8, $y + 0.8);
        $pdf->MultiCell($w - 1.6, 2.6,
            $this->txt($bloco['disciplina_nome'] ?? ''), 0, 'L');

        // Horário real do bloco
        if ($h - $faixa > 7) {
            $pdf->SetFont('Helvetica', '', 5.5);
            $pdf->SetTextColor(51, 65, 85);
            $pdf->SetXY($x + 0.8, $y + $h - $faixa - 3.0);
            $pdf->Cell($w - 1.6, 2.6, $this->txt(
                TimeHelper::fromMinutes((int)$bloco['slot_ini']) . '-' .
                TimeHelper::fromMinutes((int)$bloco['slot_fim'])
            ), 0, 0, 'L');
        }

        // Faixa inferior com o professor, na cor secundária
        if ($bloco['professor_nome']) {
            [$r2, $g2, $b2] = $this->hexRgb($bloco['professor_cor_secundaria'] ?? ($bloco['professor_cor'] ?? '#64748b'));
            $pdf->SetFillColor($r2, $g2, $b2);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 5.5);
            $pdf->SetXY($x + 0.35, $y + $h - $faixa - 0.35);
            $pdf->Cell($w - 0.7, $faixa, $this->txt($bloco['professor_nome']), 0, 0, 'C', true);
        }

        $pdf->SetXY($x, $y);
    }

    private function hexRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return [148, 163, 184];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * UTF-8 → CP1252 (codificação das fontes padrão do FPDF).
     * Feito à mão porque mbstring é proibido no projeto e o PHP do desktop
     * Windows não habilita iconv.
     */
    private function txt(string $s): string
    {
        static $mapa3 = [
            0x2013 => '-', 0x2014 => '-', 0x2018 => "'", 0x2019 => "'",
            0x201C => '"', 0x201D => '"', 0x2026 => '...', 0x00A0 => ' ',
        ];
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($s[$i]);
            if ($c < 0x80) {
                $out .= $s[$i];
            } elseif (($c & 0xE0) === 0xC0 && $i + 1 < $len) {
                $cp = (($c & 0x1F) << 6) | (ord($s[++$i]) & 0x3F);
                $out .= $cp < 256 ? chr($cp) : '?';
            } elseif (($c & 0xF0) === 0xE0 && $i + 2 < $len) {
                $cp = (($c & 0x0F) << 12) | ((ord($s[$i + 1]) & 0x3F) << 6) | (ord($s[$i + 2]) & 0x3F);
                $i += 2;
                $out .= $mapa3[$cp] ?? ($cp < 256 ? chr($cp) : '?');
            } else {
                $i += 3;
                $out .= '?';
            }
        }
        return $out;
    }
}
