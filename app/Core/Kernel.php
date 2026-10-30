<?php

declare(strict_types=1);

namespace Barberify\Core;

use Barberify\Auth\AuthControl;
use Barberify\Auth\AuthEmpresa;
use Barberify\Domain\Sistema\TarefasService;
use Throwable;

final class Kernel
{
    private Router $router;
    /** @var array<string,callable> */
    private array $middleware = [];

    public function __construct()
    {
        $this->router = new Router();
    }

    public static function boot(): void
    {
        Env::carregar((string) BASE_PATH . '/.env');
        Config::carregar(BASE_PATH . '/config');

        date_default_timezone_set((string) Config::get('app.timezone', 'America/Sao_Paulo'));
        mb_internal_encoding('UTF-8');
        setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');

        if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => (bool) Config::get('app.https', false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            @session_start();
        }

        self::registrarTratamentoDeErros();
        self::conectarBanco();
        self::registrarTarefas();
    }

    public function despachar(Request $requisicao): Response
    {
        $rota = $this->router->encontrar($requisicao->metodo(), $requisicao->caminho());

        $this->definirRealmCsrf($requisicao->caminho());

        if ($rota === null) {
            return $this->erro(404, 'Página não encontrada', 'O endereço acessado não existe ou foi movido.');
        }

        try {
            foreach ($rota['middleware'] as $nome) {
                $middleware = $this->resolverMiddleware($nome);

                if ($middleware === null) {
                    throw new \RuntimeException('Middleware desconhecido: ' . $nome);
                }

                /** @var Response|null $resposta */
                $resposta = $middleware($requisicao);

                if ($resposta instanceof Response) {
                    \Barberify\Core\Scheduler::tick();

                    return $resposta;
                }
            }

            $handler = $rota['handler'];

            if (is_array($handler)) {
                [$classe, $metodo] = $handler;
                $controlador = new $classe();
                $resposta = $controlador->{$metodo}($requisicao, $rota['parametros']);
            } elseif (is_callable($handler)) {
                $resposta = $handler($requisicao, $rota['parametros']);
            } else {
                throw new \RuntimeException('Handler inválido.');
            }

            if (!$resposta instanceof Response) {
                throw new \RuntimeException('O controlador não retornou uma Response.');
            }

            Scheduler::tick();

            return $resposta;
        } catch (Throwable $e) {
            Logger::erro('Erro não tratado', [
                'mensagem' => $e->getMessage(),
                'arquivo'  => $e->getFile() . ':' . $e->getLine(),
            ]);

            if ((bool) Config::get('app.debug', false)) {
                return Response::html(
                    '<pre style="padding:24px;background:#111;color:#f3efe7;font-size:13px;overflow:auto">'
                    . e($e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString())
                    . '</pre>',
                    500
                );
            }

            return $this->erro(500, 'Erro interno', 'Não foi possível concluir a operação. A equipe foi notificada.');
        }
    }

    public static function erro(int $status, string $titulo, string $mensagem): Response
    {
        if (!View::existe('erros/' . $status)) {
            return Response::html('<h1>' . e($titulo) . '</h1><p>' . e($mensagem) . '</p>', $status);
        }

        $layout = View::existe('layouts/erro') ? 'erro' : '';

        return View::render('erros/' . $status, [
            'titulo'   => $titulo,
            'mensagem' => $mensagem,
            'status'   => $status,
        ], $layout)->comCabecalho('Content-Type', 'text/html; charset=utf-8');
    }

    public function roteador(): Router
    {
        return $this->router;
    }

    public function usar(string $nome, callable $middleware): void
    {
        $this->middleware[$nome] = $middleware;
    }

    private function resolverMiddleware(string $nome): ?callable
    {
        if (isset($this->middleware[$nome])) {
            return $this->middleware[$nome];
        }

        return null;
    }

    private function definirRealmCsrf(string $caminho): void
    {
        if (str_starts_with($caminho, '/control')) {
            Csrf::definirRealm('control');
        } elseif (str_starts_with($caminho, '/painel')) {
            Csrf::definirRealm('empresa');
        } elseif (str_starts_with($caminho, '/minha-conta')) {
            Csrf::definirRealm('cliente');
        } else {
            Csrf::definirRealm('publico');
        }
    }

    private static function conectarBanco(): void
    {
        $config = (array) Config::get('database', []);

        if (($config['usuario'] ?? '') === '' && ($config['socket'] ?? '') === '') {
            return; // Instalação ainda não concluída.
        }

        try {
            Db::conectar($config);
        } catch (Throwable $e) {
            Logger::erro('Falha ao conectar ao banco', ['erro' => $e->getMessage()]);
        }
    }

    private static function registrarTratamentoDeErros(): void
    {
        set_error_handler(static function (int $severidade, string $mensagem, string $arquivo = '', int $linha = 0): bool {
            if (!(error_reporting() & $severidade)) {
                return false;
            }

            throw new \ErrorException($mensagem, 0, $severidade, $arquivo, $linha);
        });

        set_exception_handler(static function (Throwable $e): void {
            Logger::erro('Exceção não capturada', [
                'mensagem' => $e->getMessage(),
                'arquivo'  => $e->getFile() . ':' . $e->getLine(),
            ]);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }

            echo '<h1>Erro interno</h1>';

            if ((bool) Config::get('app.debug', false)) {
                echo '<pre>' . e($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
            }
        });
    }

    private static function registrarTarefas(): void
    {
        if (!Db::inicializado()) {
            return;
        }

        $intervalo = (int) Config::get('app.scheduler.intervalo_segundos', 300);

        Scheduler::registrar('reservas_expiradas', min(60, $intervalo), static fn () => TarefasService::expirarReservas());
        Scheduler::registrar('saas_situacao', $intervalo, static fn () => TarefasService::atualizarSituacoesSaas());
        Scheduler::registrar('expurgo', 86400, static fn () => TarefasService::expurgar());
    }
}
