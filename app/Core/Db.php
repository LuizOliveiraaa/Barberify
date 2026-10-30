<?php

declare(strict_types=1);

namespace Barberify\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Db
{
    private static ?PDO $pdo = null;

    public static function conectar(array $config): void
    {
        $dsn = $config['socket'] !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $config['socket'], $config['database'], $config['charset'])
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);

        self::$pdo = new PDO($dsn, $config['usuario'], $config['senha'], $config['opcoes']);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('Banco de dados não inicializado.');
        }

        return self::$pdo;
    }

    public static function inicializado(): bool
    {
        return self::$pdo !== null;
    }

    public static function executar(string $sql, array $parametros = []): \PDOStatement
    {
        $inicio = microtime(true);

        try {
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute($parametros);
        } catch (PDOException $e) {
            Logger::erro('Falha ao executar SQL', [
                'sql'    => self::resumir($sql),
                'erro'   => $e->getMessage(),
                'codigo' => $e->errorInfo[1] ?? null,
            ]);
            throw $e;
        }

        $duracao = (microtime(true) - $inicio) * 1000;
        if ($duracao > 800) {
            Logger::aviso('Consulta lenta', ['ms' => round($duracao), 'sql' => self::resumir($sql)]);
        }

        return $stmt;
    }

    public static function select(string $sql, array $parametros = []): array
    {
        return self::executar($sql, $parametros)->fetchAll();
    }

    public static function um(string $sql, array $parametros = []): ?array
    {
        $linha = self::executar($sql, $parametros)->fetch();

        return $linha === false ? null : $linha;
    }

    public static function escalar(string $sql, array $parametros = []): mixed
    {
        $valor = self::executar($sql, $parametros)->fetchColumn();

        return $valor === false ? null : $valor;
    }

    public static function inserir(string $tabela, array $dados): int
    {
        $colunas = array_keys($dados);
        self::validarIdentificadores(array_merge([$tabela], $colunas));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::ident($tabela),
            implode(', ', array_map([self::class, 'ident'], $colunas)),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $colunas))
        );

        $parametros = [];
        foreach ($dados as $coluna => $valor) {
            $parametros[':' . $coluna] = $valor;
        }

        self::executar($sql, $parametros);

        return (int) self::pdo()->lastInsertId();
    }

    public static function atualizar(string $tabela, array $dados, string $condicao, array $parametrosCondicao = []): int
    {
        $colunas = array_keys($dados);
        self::validarIdentificadores(array_merge([$tabela], $colunas));

        $sets = [];
        $parametros = [];

        foreach ($dados as $coluna => $valor) {
            $sets[] = self::ident($coluna) . ' = :set_' . $coluna;
            $parametros[':set_' . $coluna] = $valor;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            self::ident($tabela),
            implode(', ', $sets),
            $condicao
        );

        $stmt = self::executar($sql, array_merge($parametros, $parametrosCondicao));

        return $stmt->rowCount();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();

        if ($pdo->inTransaction()) {
            return $fn();
        }

        $pdo->beginTransaction();

        try {
            $resultado = $fn();
            $pdo->commit();

            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Linha travada para operações de saldo/estado (SELECT ... FOR UPDATE). */
    public static function travarLinha(string $tabela, int $id): ?array
    {
        self::validarIdentificadores([$tabela]);

        return self::um(sprintf('SELECT * FROM %s WHERE id = :id FOR UPDATE', self::ident($tabela)), [':id' => $id]);
    }

    public static function agora(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function resumir(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return mb_substr($sql, 0, 300);
    }

    private static function ident(string $nome): string
    {
        return '`' . str_replace('`', '', $nome) . '`';
    }

    /** @param list<string> $nomes */
    private static function validarIdentificadores(array $nomes): void
    {
        foreach ($nomes as $nome) {
            if (!preg_match('/^[a-z0-9_]+$/', $nome)) {
                throw new RuntimeException('Identificador inválido: ' . $nome);
            }
        }
    }
}
