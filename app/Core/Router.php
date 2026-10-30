<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Router
{
    /** @var list<array{metodo:string,padrao:string,regex:string,handler:mixed,middleware:array}> */
    private array $rotas = [];

    public function get(string $padrao, mixed $handler, array $middleware = []): void
    {
        $this->adicionar('GET', $padrao, $handler, $middleware);
    }

    public function post(string $padrao, mixed $handler, array $middleware = []): void
    {
        $this->adicionar('POST', $padrao, $handler, $middleware);
    }

    public function adicionar(string $metodo, string $padrao, mixed $handler, array $middleware = []): void
    {
        $padrao = '/' . trim($padrao, '/');
        if ($padrao === '/') {
            $padrao = '/';
        }

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $padrao
        );

        $this->rotas[] = [
            'metodo'     => $metodo,
            'padrao'     => $padrao,
            'regex'      => '#^' . $regex . '$#',
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    /** @return array{handler:mixed,middleware:array,parametros:array}|null */
    public function encontrar(string $metodo, string $caminho): ?array
    {
        foreach ($this->rotas as $rota) {
            if ($rota['metodo'] !== $metodo) {
                continue;
            }

            if (preg_match($rota['regex'], $caminho, $matches) === 1) {
                $parametros = [];
                foreach ($matches as $chave => $valor) {
                    if (is_string($chave)) {
                        $parametros[$chave] = $valor;
                    }
                }

                return [
                    'handler'    => $rota['handler'],
                    'middleware' => $rota['middleware'],
                    'parametros' => $parametros,
                ];
            }
        }

        return null;
    }
}
