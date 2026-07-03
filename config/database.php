<?php

// Driver do banco: 'sqlite' (padrão) ou 'mysql'. Sobrescreva com a env DB_DRIVER.
// O MySQL é mantido como opção (ex.: acesso ao banco antigo/produção legada).
$driver = getenv('DB_DRIVER') ?: 'sqlite';

$root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);

if ($driver === 'mysql') {
    return [
        'driver'   => 'mysql',
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'port'     => getenv('DB_PORT') ?: '3306',
        'dbname'   => getenv('DB_NAME') ?: 'horarios_academicos',
        'user'     => getenv('DB_USER') ?: 'sga',
        'password' => getenv('DB_PASS') ?: 'Sga@12345',
        'charset'  => 'utf8mb4',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ];
}

// SQLite: um único arquivo. No desktop, o main.js aponta DB_SQLITE_PATH para %APPDATA%.
return [
    'driver'  => 'sqlite',
    'path'    => getenv('DB_SQLITE_PATH') ?: $root . '/database/sga.sqlite',
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
];
