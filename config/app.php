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

    // Geração: número máximo de tentativas
    'max_tentativas_geracao' => 5,

    // Tabelas das cores padrão para disciplinas
    'cores_disciplinas' => [
        '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e',
        '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#64748b',
    ],
];
