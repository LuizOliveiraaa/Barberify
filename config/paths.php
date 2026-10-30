<?php

declare(strict_types=1);

/**
 * Caminhos canônicos. Nenhum código usa caminho relativo fixo: tudo parte daqui,
 * para que o Layout A (public/ como raiz) e o Layout B (htdocs) funcionem sem
 * alteração de código.
 */
return [
    'base'      => BASE_PATH,
    'app'       => BASE_PATH . '/app',
    'config'    => BASE_PATH . '/config',
    'database'  => BASE_PATH . '/database',
    'resources' => BASE_PATH . '/resources',
    'storage'   => BASE_PATH . '/storage',
    'logs'      => BASE_PATH . '/storage/logs',
    'cache'     => BASE_PATH . '/storage/cache',
    'private'   => BASE_PATH . '/storage/private',
    'tmp'       => BASE_PATH . '/storage/tmp',
    'public'    => BASE_PATH . '/public',
    'views'     => BASE_PATH . '/resources/views',
    'env'       => BASE_PATH . '/.env',
    'lock'      => BASE_PATH . '/storage/instalado.lock',
    'token'     => BASE_PATH . '/storage/instalacao.token',
];
