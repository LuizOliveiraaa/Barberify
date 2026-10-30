<?php

declare(strict_types=1);

/**
 * Autoload PSR-4 mínimo para o namespace Barberify\.
 * Sem Composer em produção: a hospedagem compartilhada não deve depender de vendor/.
 */
spl_autoload_register(static function (string $classe): void {
    $prefixo = 'Barberify\\';

    if (!str_starts_with($classe, $prefixo)) {
        return;
    }

    $relativo = str_replace('\\', '/', substr($classe, strlen($prefixo)));
    $arquivo  = BASE_PATH . '/app/' . $relativo . '.php';

    if (is_file($arquivo)) {
        require $arquivo;
    }
});
