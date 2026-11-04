<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Exception\BalanceOverflowException;

final readonly class Balance
{
    private function __construct(
        private int $cents,
    ) {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromInt(int $cents): self
    {
        return new self($cents);
    }

    public function toInt(): int
    {
        return $this->cents;
    }

    public function credit(Amount $amount): self
    {
        if (PHP_INT_MAX - $amount->toInt() < $this->cents) {
            throw new BalanceOverflowException();
        }

        return new self($this->cents + $amount->toInt());
    }

    public function debit(Amount $amount): self
    {
        if (PHP_INT_MIN + $amount->toInt() > $this->cents) {
            throw new BalanceOverflowException();
        }

        return new self($this->cents - $amount->toInt());
    }
}
