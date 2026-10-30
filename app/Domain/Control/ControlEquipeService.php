<?php

declare(strict_types=1);

namespace Barberify\Domain\Control;

use Barberify\Core\Audit;
use Barberify\Core\Config;
use Barberify\Core\Db;
use Barberify\Core\Passwords;
use Barberify\Core\Validator;
use RuntimeException;

final class ControlEquipeService
{
    public function criar(array $dados, int $atorId): int
    {
        $email = mb_strtolower(trim((string) $dados['email']));

        if (Db::um('SELECT id FROM control_usuarios WHERE email = :e', [':e' => $email]) !== null) {
            throw new RuntimeException('Já existe um usuário do Control com este e-mail.');
        }

        $erroSenha = Validator::senhaForte((string) $dados['senha'], (int) Config::get('app.login.senha_control', 12));
        if ($erroSenha !== null) {
            throw new RuntimeException($erroSenha);
        }

        $papeis = array_values(array_filter(array_map('intval', (array) ($dados['papeis'] ?? []))));

        if ($papeis === []) {
            throw new RuntimeException('Selecione ao menos um papel administrativo.');
        }

        return Db::tx(function () use ($dados, $email, $papeis, $atorId): int {
            $id = Db::inserir('control_usuarios', [
                'nome'          => trim((string) $dados['nome']),
                'email'         => $email,
                'senha_hash'    => Passwords::hash((string) $dados['senha']),
                'is_proprietario' => null,
                'protegido'     => 0,
                'status'        => 'ativo',
                'cargo'         => trim((string) ($dados['cargo'] ?? '')) ?: null,
                'trocar_senha'  => 1,
                'criado_em'     => now(),
            ]);

            foreach ($papeis as $papelId) {
                if (Db::um('SELECT id FROM control_papeis WHERE id = :id AND ativo = 1', [':id' => $papelId]) === null) {
                    throw new RuntimeException('Papel inválido selecionado.');
                }

                Db::executar('INSERT IGNORE INTO control_usuario_papeis (usuario_id, papel_id) VALUES (:u, :p)', [
                    ':u' => $id, ':p' => $papelId,
                ]);
            }

            Audit::registrar('control_usuario_criado', 'control', 'control_usuarios', $id, null,
                ['nome' => $dados['nome'], 'papeis' => $papeis], 'sucesso', null, 'control', $atorId);

            return $id;
        });
    }

    public function atualizarPapeis(int $usuarioId, array $papeis, int $atorId): void
    {
        $alvo = Db::um('SELECT * FROM control_usuarios WHERE id = :id', [':id' => $usuarioId]);

        if ($alvo === null) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        if ((int) $alvo['is_proprietario'] === 1 && $usuarioId !== $atorId) {
            throw new RuntimeException('As permissões do proprietário global não podem ser alteradas por outros usuários.');
        }

        $papeis = array_values(array_filter(array_map('intval', (array) $papeis)));

        Db::tx(function () use ($usuarioId, $papeis, $atorId, $alvo): void {
            $antes = array_column(Db::select('SELECT papel_id FROM control_usuario_papeis WHERE usuario_id = :u', [':u' => $usuarioId]), 'papel_id');

            Db::executar('DELETE FROM control_usuario_papeis WHERE usuario_id = :u', [':u' => $usuarioId]);

            foreach ($papeis as $papelId) {
                Db::executar('INSERT IGNORE INTO control_usuario_papeis (usuario_id, papel_id) VALUES (:u, :p)', [
                    ':u' => $usuarioId, ':p' => $papelId,
                ]);
            }

            Audit::registrar('control_papeis_alterados', 'control', 'control_usuarios', $usuarioId,
                ['papeis' => $antes], ['papeis' => $papeis], 'sucesso', null, 'control', $atorId);
        });
    }

    public function alternarStatus(int $usuarioId, int $atorId): void
    {
        $alvo = Db::um('SELECT * FROM control_usuarios WHERE id = :id FOR UPDATE', [':id' => $usuarioId]);

        if ($alvo === null) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        if ((int) $alvo['is_proprietario'] === 1) {
            throw new RuntimeException('A conta do proprietário global não pode ser desativada.');
        }

        if ($usuarioId === $atorId) {
            throw new RuntimeException('Você não pode desativar a própria conta.');
        }

        $novo = (string) $alvo['status'] === 'ativo' ? 'inativo' : 'ativo';

        Db::atualizar('control_usuarios', ['status' => $novo], 'id = :id', [':id' => $usuarioId]);

        if ($novo === 'inativo') {
            Db::executar(
                "UPDATE control_sessoes SET revogada_em = :agora, motivo_revogacao = 'usuario_desativado'
                  WHERE usuario_id = :u AND revogada_em IS NULL",
                [':agora' => now(), ':u' => $usuarioId]
            );
        }

        Audit::registrar('control_usuario_status', 'control', 'control_usuarios', $usuarioId,
            ['status' => $alvo['status']], ['status' => $novo], 'sucesso', null, 'control', $atorId);
    }

    public function revogarSessoes(int $usuarioId, int $atorId): int
    {
        $alvo = Db::um('SELECT * FROM control_usuarios WHERE id = :id', [':id' => $usuarioId]);

        if ($alvo === null) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        if ((int) $alvo['is_proprietario'] === 1 && $usuarioId !== $atorId) {
            throw new RuntimeException('Sessões do proprietário global só podem ser revogadas por ele mesmo.');
        }

        $afetadas = Db::executar(
            "UPDATE control_sessoes SET revogada_em = :agora, motivo_revogacao = 'revogacao_administrativa'
              WHERE usuario_id = :u AND revogada_em IS NULL",
            [':agora' => now(), ':u' => $usuarioId]
        )->rowCount();

        Audit::registrar('control_sessoes_revogadas', 'control', 'control_usuarios', $usuarioId, null,
            ['sessoes' => $afetadas], 'sucesso', null, 'control', $atorId);

        return $afetadas;
    }
}
