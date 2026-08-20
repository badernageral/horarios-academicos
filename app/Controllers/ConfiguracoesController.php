<?php

namespace App\Controllers;

use App\Models\Turno;
use App\Services\TimeHelper;

/**
 * Configurações gerais do sistema. Hoje: as faixas de hora dos turnos usados
 * na grade de disponibilidade do professor e na checagem do gerador.
 */
class ConfiguracoesController extends BaseController
{
    public function index(): void
    {
        $turnos = Turno::todos();
        $this->render('configuracoes/index', [
            'turnos'  => $turnos,
            'avisos'  => self::analisar($turnos),
            'flash'   => $this->getFlash(),
        ]);
    }

    public function salvar(): void
    {
        $atuais  = Turno::todos();
        $entrada = $this->post('turno', []);

        $novos = [];
        $erros = [];

        foreach ($atuais as $chave => $t) {
            $nome   = $t['nome'];                                    // rótulo fixo
            $inicio = trim((string)($entrada[$chave]['inicio'] ?? $t['inicio']));
            $fim    = trim((string)($entrada[$chave]['fim']    ?? $t['fim']));

            if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $inicio)
                || !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $fim)) {
                $erros[] = "Horário inválido em \"{$nome}\".";
                continue;
            }
            if (TimeHelper::toMinutes($inicio) >= TimeHelper::toMinutes($fim)) {
                $erros[] = "Em \"{$nome}\", o início precisa ser antes do fim.";
                continue;
            }

            $novos[$chave] = ['nome' => $nome, 'inicio' => $inicio, 'fim' => $fim];
        }

        if ($erros) {
            $this->flash('danger', implode(' ', $erros));
            $this->redirect('/configuracoes');
            return;
        }

        Turno::salvar($novos);

        // Vãos e sobreposições não impedem salvar, mas mudam o que o gerador
        // consegue agendar — então o usuário precisa ver o aviso.
        $avisos = self::analisar($novos);
        if ($avisos) {
            $this->flash('warning', 'Turnos salvos, mas atenção: ' . implode(' ', $avisos));
        } else {
            $this->flash('success', 'Turnos atualizados.');
        }
        $this->redirect('/configuracoes');
    }

    /**
     * Detecta vãos e sobreposições entre turnos consecutivos.
     * Vão = faixa de tempo que não pertence a turno nenhum: nenhuma aula pode
     * ser agendada ali, mesmo o professor tendo marcado verde nos vizinhos.
     *
     * @return string[]
     */
    private static function analisar(array $turnos): array
    {
        $lista = [];
        foreach ($turnos as $t) {
            $lista[] = [
                'nome'   => $t['nome'],
                'inicio' => TimeHelper::toMinutes($t['inicio']),
                'fim'    => TimeHelper::toMinutes($t['fim']),
            ];
        }
        usort($lista, fn($a, $b) => $a['inicio'] <=> $b['inicio']);

        $avisos = [];
        for ($i = 1; $i < count($lista); $i++) {
            $ant = $lista[$i - 1];
            $cur = $lista[$i];

            if ($cur['inicio'] > $ant['fim']) {
                $avisos[] = sprintf(
                    'há um vão entre %s e %s (%s–%s) onde nenhuma aula pode ser agendada.',
                    $ant['nome'], $cur['nome'],
                    TimeHelper::fromMinutes($ant['fim']), TimeHelper::fromMinutes($cur['inicio'])
                );
            } elseif ($cur['inicio'] < $ant['fim']) {
                $avisos[] = sprintf(
                    '%s e %s se sobrepõem (%s–%s); uma aula nessa faixa exigirá os DOIS turnos liberados.',
                    $ant['nome'], $cur['nome'],
                    TimeHelper::fromMinutes($cur['inicio']), TimeHelper::fromMinutes($ant['fim'])
                );
            }
        }
        return $avisos;
    }
}
