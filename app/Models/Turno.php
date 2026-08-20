<?php

namespace App\Models;

use App\Core\Database;
use App\Services\TimeHelper;

/**
 * Turnos da grade de disponibilidade do professor.
 *
 * Chaves e nomes são FIXOS ('matutino'/'vespertino'/'noturno'): a chave é
 * gravada em disponibilidade_professor.turno. A tela /configuracoes edita
 * apenas as HORAS — não cria, renomeia nem remove turnos, senão linhas de
 * disponibilidade já gravadas ficariam órfãs.
 */
class Turno extends BaseModel
{
    protected static string $table = 'turnos';
    protected static string $primaryKey = 'chave';

    /**
     * [chave => ['nome' => ..., 'inicio' => 'HH:MM', 'fim' => 'HH:MM']]
     * Mesmo formato que antes vinha de config/app.php, para os consumidores
     * (gerador, formulário do professor) não precisarem saber a origem.
     */
    public static function todos(): array
    {
        try {
            $linhas = Database::fetchAll("SELECT * FROM turnos ORDER BY ordem, hora_inicio");
        } catch (\Throwable $e) {
            $linhas = [];   // banco ainda sem a migration 002
        }

        if (empty($linhas)) return self::padrao();

        $out = [];
        foreach ($linhas as $l) {
            $out[$l['chave']] = [
                'nome'   => $l['nome'],
                'inicio' => substr($l['hora_inicio'], 0, 5),
                'fim'    => substr($l['hora_fim'], 0, 5),
            ];
        }
        return $out;
    }

    /** Fallback usado enquanto a migration 002 não rodou. */
    public static function padrao(): array
    {
        $cfg = require ROOT_PATH . '/config/app.php';
        return $cfg['turnos'] ?? [];
    }

    /**
     * Grava as faixas de hora. O NOME não é editável: é rótulo fixo, como as
     * chaves — só as horas mudam.
     *
     * @param array $turnos [chave => ['inicio' => 'HH:MM', 'fim' => 'HH:MM']]
     */
    public static function salvar(array $turnos): void
    {
        foreach ($turnos as $chave => $t) {
            Database::query(
                "UPDATE turnos SET hora_inicio = ?, hora_fim = ? WHERE chave = ?",
                [TimeHelper::toHms($t['inicio']), TimeHelper::toHms($t['fim']), $chave]
            );
        }
    }
}
