<?php

declare(strict_types=1);

namespace Barberify\Core;

use RuntimeException;
use Throwable;

final class Migrator
{
    public function __construct(private readonly string $diretorio)
    {
    }

    public function garantirTabela(): void
    {
        Db::executar(
            'CREATE TABLE IF NOT EXISTS `migracoes` (
                `versao` VARCHAR(20) NOT NULL,
                `arquivo` VARCHAR(180) NOT NULL,
                `checksum` CHAR(64) NOT NULL,
                `aplicada_em` DATETIME NOT NULL,
                `duracao_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`versao`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<array{versao:string,arquivo:string,caminho:string,checksum:string}> */
    public function arquivos(): array
    {
        $lista = [];

        foreach (glob($this->diretorio . '/*.sql') ?: [] as $caminho) {
            $arquivo = basename($caminho);
            if (!preg_match('/^(\d{3})_/', $arquivo, $m)) {
                throw new RuntimeException('Migration com nome inválido: ' . $arquivo);
            }

            $lista[] = [
                'versao'   => $m[1],
                'arquivo'  => $arquivo,
                'caminho'  => $caminho,
                'checksum' => hash_file('sha256', $caminho) ?: '',
            ];
        }

        usort($lista, static fn (array $a, array $b): int => strcmp($a['versao'], $b['versao']));

        return $lista;
    }

    /** @return list<string> */
    public function pendentes(): array
    {
        $this->garantirTabela();

        $aplicadas = [];
        foreach (Db::select('SELECT versao, checksum, arquivo FROM migracoes') as $linha) {
            $aplicadas[(string) $linha['versao']] = $linha;
        }

        $pendentes = [];
        foreach ($this->arquivos() as $arquivo) {
            if (isset($aplicadas[$arquivo['versao']])) {
                $registrada = $aplicadas[$arquivo['versao']];
                if (!hash_equals((string) $registrada['checksum'], $arquivo['checksum'])) {
                    throw new RuntimeException(sprintf(
                        'A migration %s foi alterada depois de aplicada (checksum divergente). Crie uma nova migration.',
                        $arquivo['arquivo']
                    ));
                }
                continue;
            }

            $pendentes[] = $arquivo['arquivo'];
        }

        return $pendentes;
    }

    /** @return list<string> */
    public function aplicar(?callable $log = null): array
    {
        $this->garantirTabela();
        $aplicadas = [];

        foreach ($this->arquivos() as $arquivo) {
            $existente = Db::um('SELECT versao, checksum FROM migracoes WHERE versao = :v', [':v' => $arquivo['versao']]);

            if ($existente !== null) {
                if (!hash_equals((string) $existente['checksum'], $arquivo['checksum'])) {
                    throw new RuntimeException('Checksum divergente em ' . $arquivo['arquivo']);
                }
                continue;
            }

            $inicio = microtime(true);

            try {
                foreach ($this->dividir((string) file_get_contents($arquivo['caminho'])) as $instrucao) {
                    Db::pdo()->exec($instrucao);
                }
            } catch (Throwable $e) {
                throw new RuntimeException(
                    sprintf('Falha na migration %s: %s', $arquivo['arquivo'], $e->getMessage()),
                    0,
                    $e
                );
            }

            $duracao = (int) round((microtime(true) - $inicio) * 1000);

            Db::inserir('migracoes', [
                'versao'      => $arquivo['versao'],
                'arquivo'     => $arquivo['arquivo'],
                'checksum'    => $arquivo['checksum'],
                'aplicada_em' => now(),
                'duracao_ms'  => $duracao,
            ]);

            $aplicadas[] = $arquivo['arquivo'];

            if ($log !== null) {
                $log(sprintf('%s aplicada em %d ms', $arquivo['arquivo'], $duracao));
            }
        }

        return $aplicadas;
    }

    /** @return list<string> */
    private function dividir(string $sql): array
    {
        // Remove comentários de linha e divide em instruções.
        $linhas = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $linha) {
            if (preg_match('/^\s*--/', $linha)) {
                continue;
            }
            $linhas[] = $linha;
        }

        $instrucoes = [];
        foreach (explode(';', implode("\n", $linhas)) as $instrucao) {
            $instrucao = trim($instrucao);
            if ($instrucao !== '') {
                $instrucoes[] = $instrucao;
            }
        }

        return $instrucoes;
    }
}
