<?php

declare(strict_types=1);

namespace Barberify\Http;

use Barberify\Auth\AuthControl;
use Barberify\Auth\AuthEmpresa;
use Barberify\Core\Audit;
use Barberify\Core\Config;
use Barberify\Core\Csrf;
use Barberify\Core\Db;
use Barberify\Core\Kernel;
use Barberify\Core\Request;
use Barberify\Core\Response;
use Barberify\Core\View;

final class Middleware
{
    public static function registrar(Kernel $kernel): void
    {
        $kernel->usar('instalado', static function (Request $r): ?Response {
            if (is_file((string) Config::get('paths.lock'))) {
                return null;
            }

            return Response::redirect(url('/instalacao'));
        });

        $kernel->usar('csrf', static function (Request $r): ?Response {
            if (!in_array($r->metodo(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return null;
            }

            $token = $r->entrada('_token');

            if (!Csrf::validar(is_string($token) ? $token : null) || !$r->mesmaOrigem()) {
                Audit::registrar('csrf_invalido', 'seguranca', 'requisicao', null, null, [
                    'caminho' => $r->caminho(),
                    'metodo'  => $r->metodo(),
                ], 'negado');

                return Kernel::erro(419, 'Sessão expirada', 'Recarregue a página e tente novamente.');
            }

            return null;
        });

        $kernel->usar('control.convidado', static function (Request $r): ?Response {
            $auth = AuthControl::instancia();

            if ($auth->autenticado()) {
                return Response::redirect(url('/control'));
            }

            if ($auth->pendente2fa()) {
                return Response::redirect(url('/control/2fa'));
            }

            return null;
        });

        $kernel->usar('control.autenticado', static function (Request $r): ?Response {
            $auth = AuthControl::instancia();

            if ($auth->pendente2fa()) {
                return Response::redirect(url('/control/2fa'));
            }

            if (!$auth->autenticado()) {
                return Response::redirect(url('/control/login'));
            }

            return null;
        });

        $kernel->usar('control.proprietario', static function (Request $r): ?Response {
            $auth = AuthControl::instancia();

            if (!$auth->autenticado()) {
                return Response::redirect(url('/control/login'));
            }

            if (!$auth->ehProprietario()) {
                Audit::registrar('acesso_negado', 'control', 'area_proprietario', null, null, null, 'negado');

                return self::proibido('Esta área é exclusiva do proprietário da plataforma.');
            }

            return null;
        });

        $kernel->usar('empresa.convidado', static function (Request $r): ?Response {
            if (AuthEmpresa::instancia()->autenticado()) {
                return Response::redirect(url('/painel'));
            }

            return null;
        });

        $kernel->usar('empresa.autenticado', static function (Request $r): ?Response {
            if (!AuthEmpresa::instancia()->autenticado()) {
                return Response::redirect(url('/painel/login'));
            }

            return null;
        });

        $kernel->usar('empresa.assinatura', static function (Request $r): ?Response {
            $empresaId = AuthEmpresa::instancia()->empresaId();

            if ($empresaId === null) {
                return Response::redirect(url('/painel/login'));
            }

            $bloqueio = Db::um(
                'SELECT e.status AS empresa_status, a.status AS assinatura_status
                   FROM empresas e
                   LEFT JOIN saas_assinaturas a ON a.empresa_id = e.id
                  WHERE e.id = :id
                  ORDER BY a.id DESC
                  LIMIT 1',
                [':id' => $empresaId]
            );

            if ($bloqueio === null) {
                return null;
            }

            $suspensa = (string) $bloqueio['empresa_status'] === 'suspensa'
                || in_array((string) $bloqueio['assinatura_status'], ['suspensa', 'cancelada'], true);

            if ($suspensa && !str_starts_with($r->caminho(), '/painel/assinatura') && $r->caminho() !== '/painel/sair') {
                return Response::redirect(url('/painel/assinatura'));
            }

            return null;
        });

        $kernel->usar('permissao', static function (Request $r): ?Response {
            return null; // substituído por permissao:chave nas rotas
        });
    }

    public static function permissaoControl(string $chave): callable
    {
        return static function (Request $r) use ($chave): ?Response {
            $auth = AuthControl::instancia();

            if (!$auth->autenticado()) {
                return Response::redirect(url('/control/login'));
            }

            if (!$auth->pode($chave)) {
                Audit::registrar('acesso_negado', 'control', 'permissao', null, null, ['chave' => $chave], 'negado');

                return self::proibido('Você não tem permissão para acessar esta área.');
            }

            return null;
        };
    }

    public static function permissaoEmpresa(string $chave): callable
    {
        return static function (Request $r) use ($chave): ?Response {
            if (!AuthEmpresa::instancia()->autenticado()) {
                return Response::redirect(url('/painel/login'));
            }

            if (!\Barberify\Core\Contexto::pode($chave)) {
                Audit::registrar('acesso_negado', 'empresa', 'permissao', null, null, ['chave' => $chave], 'negado');

                return self::proibido('Seu usuário não tem permissão para esta ação.');
            }

            return null;
        };
    }

    private static function proibido(string $mensagem): Response
    {
        return Kernel::erro(403, 'Acesso negado', $mensagem);
    }
}
