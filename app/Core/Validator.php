<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Validator
{
    /** @var array<string,string> */
    private array $erros = [];
    /** @var array<string,mixed> */
    private array $dadosLimpos = [];

    /**
     * @param array<string,mixed> $dados
     * @param array<string,string> $regras Campo => "obrigatorio|email|min:3"
     * @param array<string,string> $rotulos Campo => "Nome"
     */
    public static function validar(array $dados, array $regras, array $rotulos = []): self
    {
        $v = new self();

        foreach ($regras as $campo => $conjunto) {
            $valor   = $dados[$campo] ?? null;
            $rotulo  = $rotulos[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
            $lista   = explode('|', $conjunto);
            $limpo   = is_string($valor) ? trim($valor) : $valor;
            $parou   = false;

            foreach ($lista as $regra) {
                if ($parou) {
                    break;
                }

                [$nome, $parametro] = array_pad(explode(':', $regra, 2), 2, null);

                switch ($nome) {
                    case 'obrigatorio':
                        if ($limpo === null || $limpo === '' || $limpo === []) {
                            $v->erros[$campo] = $rotulo . ' é obrigatório.';
                            $parou = true;
                        }
                        break;

                    case 'email':
                        if ($limpo !== null && $limpo !== '' && filter_var((string) $limpo, FILTER_VALIDATE_EMAIL) === false) {
                            $v->erros[$campo] = 'Informe um e-mail válido.';
                            $parou = true;
                        }
                        break;

                    case 'min':
                        if ($limpo !== null && $limpo !== '' && mb_strlen((string) $limpo) < (int) $parametro) {
                            $v->erros[$campo] = sprintf('%s deve ter ao menos %d caracteres.', $rotulo, (int) $parametro);
                            $parou = true;
                        }
                        break;

                    case 'max':
                        if ($limpo !== null && mb_strlen((string) $limpo) > (int) $parametro) {
                            $v->erros[$campo] = sprintf('%s deve ter no máximo %d caracteres.', $rotulo, (int) $parametro);
                            $parou = true;
                        }
                        break;

                    case 'inteiro':
                        if ($limpo !== null && $limpo !== '' && filter_var($limpo, FILTER_VALIDATE_INT) === false) {
                            $v->erros[$campo] = $rotulo . ' deve ser um número inteiro.';
                            $parou = true;
                        }
                        break;

                    case 'decimal':
                        if ($limpo !== null && $limpo !== '' && !preg_match('/^\d+(?:[.,]\d{1,2})?$/', (string) $limpo)) {
                            $v->erros[$campo] = $rotulo . ' deve ser um valor numérico válido.';
                            $parou = true;
                        }
                        break;

                    case 'data':
                        if ($limpo !== null && $limpo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $limpo)) {
                            $v->erros[$campo] = $rotulo . ' deve ser uma data válida.';
                            $parou = true;
                        }
                        break;

                    case 'telefone':
                        $digitos = preg_replace('/\D+/', '', (string) $limpo) ?? '';
                        if ($limpo !== null && $limpo !== '' && (strlen($digitos) < 10 || strlen($digitos) > 11)) {
                            $v->erros[$campo] = $rotulo . ' deve ter DDD + número.';
                            $parou = true;
                        }
                        break;

                    case 'in':
                        $opcoes = explode(',', (string) $parametro);
                        if ($limpo !== null && $limpo !== '' && !in_array((string) $limpo, $opcoes, true)) {
                            $v->erros[$campo] = $rotulo . ' possui valor inválido.';
                            $parou = true;
                        }
                        break;

                    case 'confirmado':
                        if (($dados[$campo . '_confirmacao'] ?? null) !== $valor) {
                            $v->erros[$campo] = 'A confirmação não confere.';
                            $parou = true;
                        }
                        break;
                }
            }

            $v->dadosLimpos[$campo] = $limpo;
        }

        return $v;
    }

    public static function senhaForte(string $senha, int $minimo = 10): ?string
    {
        if (mb_strlen($senha) < $minimo) {
            return sprintf('A senha deve ter ao menos %d caracteres.', $minimo);
        }

        if (!preg_match('/[a-zA-Z]/', $senha) || !preg_match('/\d/', $senha)) {
            return 'A senha deve conter letras e números.';
        }

        $comuns = (array) Config::get('security.senhas_comuns', []);
        if (in_array(mb_strtolower($senha), $comuns, true)) {
            return 'Esta senha é muito comum. Escolha outra.';
        }

        return null;
    }

    public function falhou(): bool
    {
        return $this->erros !== [];
    }

    /** @return array<string,string> */
    public function erros(): array
    {
        return $this->erros;
    }

    public function primeiroErro(): ?string
    {
        return $this->erros === [] ? null : (string) reset($this->erros);
    }

    public function dados(): array
    {
        return $this->dadosLimpos;
    }

    public function valor(string $campo, mixed $padrao = null): mixed
    {
        return $this->dadosLimpos[$campo] ?? $padrao;
    }
}
