<?php

declare(strict_types=1);

namespace Barberify\Core;

final class Paginator
{
    public readonly int $pagina;
    public readonly int $porPagina;
    public readonly int $total;
    public readonly int $ultimaPagina;

    public function __construct(int $total, int $pagina = 1, int $porPagina = 20)
    {
        $this->total       = max(0, $total);
        $this->porPagina   = max(1, $porPagina);
        $this->ultimaPagina = max(1, (int) ceil($this->total / $this->porPagina));
        $this->pagina      = min(max(1, $pagina), $this->ultimaPagina);
    }

    public function offset(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }

    public function de(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function ate(): int
    {
        return min($this->offset() + $this->porPagina, $this->total);
    }

    /** @return list<int> */
    public function janela(int $alcance = 2): array
    {
        $inicio = max(1, $this->pagina - $alcance);
        $fim    = min($this->ultimaPagina, $this->pagina + $alcance);

        return range($inicio, $fim);
    }

    public function temAnterior(): bool
    {
        return $this->pagina > 1;
    }

    public function temProxima(): bool
    {
        return $this->pagina < $this->ultimaPagina;
    }
}
