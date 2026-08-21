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

    /**
     * PDF em formato AGENDA (aulas listadas por dia), um grupo por página.
     * Usado pelos escopos professor e sala, que não cabem na malha por turma:
     * um professor atravessa cursos com turnos e durações de aula diferentes.
     *
     * @param array $grupos [nome => ['cor','cor_sec','dias'=>[dia=>[horario,...]]]]
     * @param array $dias   [num => rótulo]
     */
    public function gerarAgenda(array $grupos, array $dias, string $titulo, string $nomeArquivo): void
    {
        if (empty($grupos)) {
            $this->pdf->AddPage();
            $this->pdf->SetFont('Helvetica', '', 12);
            $this->pdf->Cell(0, 10, $this->txt('Nenhum horário nesta geração.'), 0, 1);
            $this->pdf->Output('D', $nomeArquivo . '.pdf');
            return;
        }

        $util   = $this->pdf->GetPageWidth() - 2 * $this->margem;
        $colDia = $util / max(1, count($dias));

        foreach ($grupos as $nome => $g) {
            $this->pdf->AddPage();
            $this->desenharAgenda((string)$nome, $g, $dias, $colDia, $titulo);
        }

        $this->pdf->Output('D', $nomeArquivo . '.pdf');
    }

    private function desenharAgenda(string $nome, array $g, array $dias, float $colDia, string $titulo): void
    {
        $pdf = $this->pdf;
        $x0  = $this->margem;
        $y   = $this->margem;

        // Cabeçalho: faixa na cor do professor + nome
        [$r, $vg, $b] = $this->hexRgb($g['cor'] ?? '#94a3b8');
        $pdf->SetFillColor($r, $vg, $b);
        $pdf->Rect($x0, $y, 4, 7, 'F');

        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetXY($x0 + 6, $y);
        $pdf->Cell(0, 7, $this->txt($nome), 0, 1);

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetXY($x0, $y + 7.5);
        $pdf->Cell(0, 4, $this->txt($titulo), 0, 1);

        $y += 13.5;

        // Cabeçalho dos dias
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetTextColor(30, 41, 59);
        $x = $x0;
        foreach ($dias as $rotulo) {
            $pdf->SetXY($x, $y);
            $pdf->Cell($colDia, 6, $this->txt($rotulo), 1, 0, 'C', true);
            $x += $colDia;
        }
        $y += 6;

        // Corpo: uma coluna por dia, aulas empilhadas
        $alturaCorpo = $this->pdf->GetPageHeight() - $y - $this->margem;
        $x = $x0;
        foreach ($dias as $dNum => $rotulo) {
            $pdf->Rect($x, $y, $colDia, $alturaCorpo);
            $aulas = $g['dias'][$dNum] ?? [];
            $yy    = $y + 1.5;

            if (empty($aulas)) {
                $pdf->SetFont('Helvetica', 'I', 8);
                $pdf->SetTextColor(203, 213, 225);
                $pdf->SetXY($x, $yy + 2);
                $pdf->Cell($colDia, 4, $this->txt('—'), 0, 0, 'C');
            }

            foreach ($aulas as $h) {
                $altura = 13.0;
                if ($yy + $altura > $y + $alturaCorpo) break;   // não vaza da página

                [$cr, $cg, $cb] = $this->hexRgb($g['cor'] ?? '#94a3b8');
                $pdf->SetFillColor(
                    (int)round(255 - (255 - $cr) * 0.15),
                    (int)round(255 - (255 - $cg) * 0.15),
                    (int)round(255 - (255 - $cb) * 0.15)
                );
                $pdf->Rect($x + 1, $yy, $colDia - 2, $altura - 1.5, 'F');
                $pdf->SetFillColor($cr, $cg, $cb);
                $pdf->Rect($x + 1, $yy, 1.2, $altura - 1.5, 'F');

                $pdf->SetTextColor(30, 41, 59);
                $pdf->SetFont('Helvetica', 'B', 7.5);
                $pdf->SetXY($x + 3, $yy + 0.6);
                $pdf->Cell($colDia - 4, 3,
                    $this->txt(substr($h['hora_inicio'], 0, 5) . '-' . substr($h['hora_fim'], 0, 5)), 0, 1);

                $pdf->SetFont('Helvetica', '', 7);
                $pdf->SetXY($x + 3, $yy + 3.9);
                $pdf->Cell($colDia - 4, 3, $this->txt((string)$h['disciplina_nome']), 0, 1);

                $pdf->SetFont('Helvetica', '', 6);
                $pdf->SetTextColor(100, 116, 139);
                $detalhe = ($h['curso_nome'] ?? '') . ' - ' . ($h['turma_nome'] ?? '');
                if (!empty($h['sala_nome'])) $detalhe .= ' - ' . $h['sala_nome'];
                $pdf->SetXY($x + 3, $yy + 7.0);
                $pdf->Cell($colDia - 4, 3, $this->txt($detalhe), 0, 1);

                if (!empty($h['observacao'])) {
                    $pdf->SetFont('Helvetica', 'I', 6);
                    $pdf->SetTextColor(71, 85, 105);
                    $pdf->SetXY($x + 3, $yy + 9.8);
                    $pdf->Cell($colDia - 4, 3, $this->txt((string)$h['observacao']), 0, 1);
                }

                $yy += $altura;
            }
            $x += $colDia;
        }
    }

    private function desenharTurma(array $turma): void
    {
        $pdf = $this->pdf;
        $alturaUtil = $pdf->GetPageHeight() - 2 * $this->margem;

        // Cabeçalho da turma (faixa escura, como na tela)
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(241, 245, 249);
        $pdf->SetFont('Helvetica', 'B', 10);
        $rotuloTurma = $turma['curso_nome'] . ' - ' . $turma['turma_nome'];
        if (!empty($turma['anotacao'])) $rotuloTurma .= '   -   ' . $turma['anotacao'];
        $pdf->Cell($this->colHora + 5 * $this->colDia, 7, $this->txt($rotuloTurma), 1, 1, 'L', true);

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

        // Nome da disciplina (quebra em linhas dentro do bloco).
        // Texto por contraste sobre o MESMO fundo composto a 35%, como na tela.
        [$tr, $tg, $tb] = ColorHelper::textoSobreRgb($bloco['professor_cor'] ?? '#94a3b8', 0.35);
        $pdf->SetTextColor($tr, $tg, $tb);
        $pdf->SetFont('Helvetica', 'B', 6.5);
        $pdf->SetXY($x + 0.8, $y + 0.8);
        $pdf->MultiCell($w - 1.6, 2.6,
            $this->txt($bloco['disciplina_nome'] ?? ''), 0, 'L');


        // Horário real do bloco
        if ($h - $faixa > 7) {
            $pdf->SetFont('Helvetica', '', 5.5);
            $pdf->SetTextColor(51, 65, 85);
            $pdf->SetXY($x + 0.8, $y + $h - $faixa - 3.0);
            $hora = TimeHelper::fromMinutes((int)$bloco['slot_ini']) . '-' .
                    TimeHelper::fromMinutes((int)$bloco['slot_fim']);
            $pdf->Cell($w - 1.6, 2.6, $this->txt($hora), 0, 0, 'L');

            // Anotação na MESMA linha do horário (o bloco de 1 aula não comporta
            // uma linha extra). Recuada pela largura do horário já impresso.
            if (!empty($bloco['observacao'])) {
                $recuo = $pdf->GetStringWidth($this->txt($hora)) + 1.2;
                $pdf->SetFont('Helvetica', 'BI', 5.5);
                $pdf->SetXY($x + 0.8 + $recuo, $y + $h - $faixa - 3.0);
                $pdf->Cell($w - 1.6 - $recuo, 2.6, $this->txt((string)$bloco['observacao']), 0, 0, 'L');
            }
        }

        // Faixa inferior com o professor, na cor secundária
        if ($bloco['professor_nome']) {
            $corFaixa = $bloco['professor_cor_secundaria'] ?? ($bloco['professor_cor'] ?? '#64748b');
            [$r2, $g2, $b2] = $this->hexRgb($corFaixa);
            $pdf->SetFillColor($r2, $g2, $b2);
            // Branco fixo sumia em secundárias claras (âmbar, lima, índigo claro).
            [$fr, $fg, $fb] = ColorHelper::textoSobreRgb($corFaixa);
            $pdf->SetTextColor($fr, $fg, $fb);
            $pdf->SetFont('Helvetica', 'B', 5.5);
            // Fundo da faixa em toda a largura...
            $pdf->SetXY($x + 0.35, $y + $h - $faixa - 0.35);
            $pdf->Cell($w - 0.7, $faixa, '', 0, 0, 'L', true);
            // ...e o nome alinhado à esquerda, como na tela (.disc-faixa não
            // tem text-align, então o padrão é left).
            $pdf->SetXY($x + 1.2, $y + $h - $faixa - 0.35);
            $pdf->Cell($w - 2.4, $faixa, $this->txt($bloco['professor_nome']), 0, 0, 'L');
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
