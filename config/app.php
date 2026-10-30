<?php

declare(strict_types=1);

use Barberify\Core\Env;

$url = trim((string) Env::get('APP_URL', ''));

return [
    'nome'      => 'Barberify',
    'empresa'   => 'Soluções AL',
    'versao'    => '0.3.0',
    'ambiente'  => Env::get('APP_ENV', 'producao'), // producao | homologacao | local
    'debug'     => Env::bool('APP_DEBUG', false),
    'url'       => rtrim($url !== '' ? $url : 'http://localhost:8080', '/'),
    'https'     => Env::bool('APP_HTTPS', false),
    'timezone'  => 'America/Sao_Paulo',
    'locale'    => 'pt_BR',
    'moeda'     => 'BRL',

    'sessao' => [
        // Os três realms são independentes: cookie, tabela e tempo próprios.
        'control' => ['cookie' => 'bf_ctl', 'inativo' => 1800,  'absoluto' => 28800,  'same_site' => 'Strict'],
        'empresa' => ['cookie' => 'bf_emp', 'inativo' => 28800, 'absoluto' => 43200,  'same_site' => 'Lax'],
        'cliente' => ['cookie' => 'bf_cli', 'inativo' => 2592000, 'absoluto' => 5184000, 'same_site' => 'Lax'],
    ],

    'login' => [
        'max_tentativas'      => (int) Env::get('LOGIN_MAX_TENTATIVAS', '5'),
        'janela_minutos'      => 15,
        'bloqueio_minutos'    => 15,
        'senha_minima'        => 10,
        'senha_control'       => 12,
    ],

    'upload' => [
        'max_bytes' => 2 * 1024 * 1024,
        'mimes'     => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'paginacao' => ['por_pagina' => 20],

    'scheduler' => [
        // Executa no máximo a cada N segundos, dentro de requisições reais.
        'intervalo_segundos' => 300,
        'orcamento_ms'       => 2000,
        'amostragem'         => 10, // 1 a cada N requisições
    ],
];
