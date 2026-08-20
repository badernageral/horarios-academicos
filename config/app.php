<?php

return [
    'name'     => 'Horários Acadêmicos',
    'version'  => '1.0.0',
    'debug'    => getenv('APP_DEBUG') === 'true' || true,
    'timezone' => 'America/Sao_Paulo',
    'locale'   => 'pt_BR',

    // Dias da semana (1=Segunda...6=Sábado)
    'dias_semana' => [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
    ],

    'dias_semana_abrev' => [
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
    ],

    'tipos_sala' => [
        'sala'        => 'Sala de Aula',
        'laboratorio' => 'Laboratório',
        'auditorio'   => 'Auditório',
        'quadra'      => 'Quadra',
        'biblioteca'  => 'Biblioteca',
        'outro'       => 'Outro',
        'qualquer'    => 'Qualquer',
    ],

    // FALLBACK dos turnos. A fonte real é a tabela `turnos` (editável em
    // /configuracoes); estes valores só valem enquanto a migration 002 não
    // rodou, e servem de semente para bancos novos.
    // Turnos usados na grade de disponibilidade do professor (3 linhas × 5 dias).
    // As faixas são CONTÍGUAS de propósito: um vão entre turnos (ex.: 12:00–13:00)
    // criaria uma zona morta onde nenhuma aula seria permitida, mesmo o professor
    // tendo marcado verde nos dois turnos vizinhos.
    'turnos' => [
        'matutino'   => ['nome' => 'Matutino',   'inicio' => '07:00', 'fim' => '12:00'],
        'vespertino' => ['nome' => 'Vespertino', 'inicio' => '12:00', 'fim' => '18:00'],
        'noturno'    => ['nome' => 'Noturno',    'inicio' => '18:00', 'fim' => '23:00'],
    ],

    // Estados de cada retângulo da grade de disponibilidade.
    // 0 = não pode; 1 = pode (preferencial); 2 = pode se não houver verde livre.
    'disp_estados' => [
        0 => ['rotulo' => 'Não pode',      'icone' => 'bi-x-lg',        'classe' => 'disp-nao'],
        1 => ['rotulo' => 'Pode',          'icone' => 'bi-check-lg',    'classe' => 'disp-sim'],
        2 => ['rotulo' => 'Só se precisar','icone' => 'bi-question-lg', 'classe' => 'disp-talvez'],
    ],

    // Geração: número máximo de tentativas
    'max_tentativas_geracao' => 5,

    // Tabelas das cores padrão para disciplinas
    'cores_disciplinas' => [
        '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e',
        '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#64748b',
    ],
];
