<?php

declare(strict_types=1);

use Barberify\Core\Env;

return [
    'host'     => Env::get('DB_HOST', '127.0.0.1'),
    'port'     => (int) Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'barberify'),
    'usuario'  => Env::get('DB_USUARIO', ''),
    'senha'    => Env::get('DB_SENHA', ''),
    'charset'  => 'utf8mb4',
    // Socket opcional (útil em homologação local); vazio usa host/porta.
    'socket'   => Env::get('DB_SOCKET', ''),
    'opcoes'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
];
