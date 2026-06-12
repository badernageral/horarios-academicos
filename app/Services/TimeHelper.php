<?php

namespace App\Services;

class TimeHelper
{
    /** "HH:MM" or "HH:MM:SS" → minutos desde meia-noite */
    public static function toMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return (int)$parts[0] * 60 + (int)$parts[1];
    }

    /** Minutos desde meia-noite → "HH:MM" */
    public static function fromMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    /** Duração entre dois tempos em minutos */
    public static function duration(string $inicio, string $fim): int
    {
        return self::toMinutes($fim) - self::toMinutes($inicio);
    }

    /** Formata duração em minutos para exibição legível */
    public static function formatDuration(int $minutes): string
    {
        if ($minutes < 60) return "{$minutes}min";
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $m > 0 ? "{$h}h{$m}min" : "{$h}h";
    }

    /**
     * Verifica se dois intervalos [s1,e1) e [s2,e2) se sobrepõem.
     * Trata fim igual a início como adjacente (sem sobreposição).
     */
    public static function overlaps(int $s1, int $e1, int $s2, int $e2): bool
    {
        return $s1 < $e2 && $s2 < $e1;
    }

    /**
     * Verifica se [start, end) se sobrepõe a algum intervalo na lista.
     * $intervals = [[inicio => int, fim => int], ...]
     */
    public static function overlapsAny(int $start, int $end, array $intervals): bool
    {
        foreach ($intervals as $i) {
            if (self::overlaps($start, $end, $i['inicio'], $i['fim'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dado um conjunto de intervalos ocupados, retorna a lista de inícios
     * candidatos onde uma nova atividade poderia caber.
     */
    public static function possiveisInicios(array $ocupados, int $turnoInicio): array
    {
        $inicios = [$turnoInicio];
        foreach ($ocupados as $i) {
            $inicios[] = $i['fim'];
        }
        return array_unique($inicios);
    }

    /**
     * Mescla duas listas de intervalos ocupados removendo duplicatas.
     */
    public static function mergeOcupados(array $a, array $b): array
    {
        return array_merge($a, $b);
    }

    /**
     * Calcula o total de minutos de janela (gaps entre aulas) para uma lista de intervalos.
     */
    public static function calcularJanelas(array $intervals): int
    {
        if (count($intervals) <= 1) return 0;

        usort($intervals, fn($a, $b) => $a['inicio'] - $b['inicio']);

        $janelas = 0;
        $prevFim = $intervals[0]['fim'];

        for ($i = 1; $i < count($intervals); $i++) {
            $gap = $intervals[$i]['inicio'] - $prevFim;
            if ($gap > 0) {
                $janelas += $gap;
            }
            $prevFim = max($prevFim, $intervals[$i]['fim']);
        }

        return $janelas;
    }

    /**
     * Retorna o total de minutos de aulas em um dia.
     */
    public static function totalMinutosDia(array $intervals): int
    {
        return array_sum(array_map(
            fn($i) => $i['fim'] - $i['inicio'],
            $intervals
        ));
    }
}
