<?php

declare(strict_types=1);

use Barberify\Core\Env;

return [
    // Chave usada para CSRF, cifra do segredo TOTP e tokens assinados.
    'app_key'        => Env::get('APP_KEY', ''),
    'owner_recovery' => Env::get('OWNER_RECOVERY_KEY', ''),

    'senhas_comuns' => [
        '123456789', '12345678', 'senha123', 'password', 'barberify', 'barbearia',
        'qwerty123', 'admin123', '1234567890', 'senha1234', 'abcd1234', 'mudar123',
    ],

    'cabecalhos' => [
        'X-Content-Type-Options'   => 'nosniff',
        'X-Frame-Options'          => 'DENY',
        'Referrer-Policy'          => 'strict-origin-when-cross-origin',
        'Permissions-Policy'       => 'geolocation=(self), microphone=(), camera=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Content-Security-Policy'  => "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'",
    ],
];
