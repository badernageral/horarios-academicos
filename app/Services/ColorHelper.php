<?php

namespace App\Services;

/**
 * Escolha de cor de texto por contraste (WCAG 2.x, luminância relativa).
 *
 * Existe porque as cores dos professores vêm de uma paleta de 50 tons que vai
 * do amarelo claro ao índigo escuro: texto fixo (preto OU branco) fica sempre
 * ilegível em uma das pontas.
 */
class ColorHelper
{
    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        $h = ltrim(trim($hex), '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (strlen($h) !== 6 || !ctype_xdigit($h)) {
            return [128, 128, 128];
        }
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /**
     * Luminância relativa (0 = preto, 1 = branco).
     *
     * $alpha < 1 compõe a cor sobre BRANCO antes de medir: é o caso dos blocos
     * da grade, cujo fundo é a cor com ~35% de opacidade sobre a célula branca.
     * Medir a cor pura ali daria um resultado que não corresponde ao que se vê.
     */
    public static function luminancia(string $hex, float $alpha = 1.0): float
    {
        $alpha = max(0.0, min(1.0, $alpha));
        $canais = [];

        foreach (self::rgb($hex) as $v) {
            $v = ($v * $alpha) + (255 * (1 - $alpha));   // composição sobre branco
            $v = $v / 255;
            $canais[] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }

        return 0.2126 * $canais[0] + 0.7152 * $canais[1] + 0.0722 * $canais[2];
    }

    /** '#000' ou '#fff' — o que tiver mais contraste sobre o fundo dado. */
    public static function textoSobre(string $hex, float $alpha = 1.0): string
    {
        $lum = self::luminancia($hex, $alpha);
        $comBranco = 1.05 / ($lum + 0.05);
        $comPreto  = ($lum + 0.05) / 0.05;
        return $comBranco >= $comPreto ? '#fff' : '#000';
    }

    /** Mesma decisão, em RGB — para o FPDF, que não entende hex. */
    public static function textoSobreRgb(string $hex, float $alpha = 1.0): array
    {
        return self::textoSobre($hex, $alpha) === '#fff' ? [255, 255, 255] : [0, 0, 0];
    }
}
