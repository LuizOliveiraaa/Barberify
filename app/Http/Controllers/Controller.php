<?php

declare(strict_types=1);

namespace Barberify\Http\Controllers;

use Barberify\Core\Flash;
use Barberify\Core\Request;
use Barberify\Core\Response;
use Barberify\Core\View;

abstract class Controller
{
    protected function view(string $template, array $dados = [], string $layout = 'app'): Response
    {
        return View::render($template, $dados, $layout);
    }

    protected function redirecionar(string $caminho, string $mensagem = null, string $tipo = 'sucesso'): Response
    {
        if ($mensagem !== null) {
            $tipo === 'erro' ? Flash::erro($mensagem) : ($tipo === 'aviso' ? Flash::aviso($mensagem) : Flash::sucesso($mensagem));
        }

        return Response::redirect(url($caminho));
    }

    protected function voltar(string $padrao = '/'): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === parse_url((string) \Barberify\Core\Config::get('app.url'), PHP_URL_HOST)) {
            return Response::redirect($referer);
        }

        return Response::redirect(url($padrao));
    }

    protected function entrada(Request $requisicao): array
    {
        return $requisicao->todos();
    }
}
