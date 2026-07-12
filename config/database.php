<?php

declare(strict_types=1);

use App\Support\Env;

$defaultHost = Env::get('DB_HOST', '127.0.0.1');
$defaultPort = Env::get('DB_PORT', '3306');
$defaultUser = Env::get('DB_USER', 'root');
$defaultPass = Env::get('DB_PASS', '');
$defaultCharset = Env::get('DB_CHARSET', 'utf8mb4');

return [
    'default' => [
        'host' => $defaultHost,
        'port' => $defaultPort,
        'database' => Env::get('DB_NAME', 'rhhgith'),
        'username' => $defaultUser,
        'password' => $defaultPass,
        'charset' => $defaultCharset,
    ],
    'rrhh' => [
        'host' => Env::get('RRHH_DB_HOST', $defaultHost),
        'port' => Env::get('RRHH_DB_PORT', $defaultPort),
        'database' => Env::get('RRHH_DB_NAME', 'rrhh2029'),
        'username' => Env::get('RRHH_DB_USER', $defaultUser),
        'password' => Env::get('RRHH_DB_PASS', $defaultPass),
        'charset' => Env::get('RRHH_DB_CHARSET', $defaultCharset),
    ],
];
