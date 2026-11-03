<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Exception\InvalidAmountException;

final readonly class Amount
{
    private function __construct(
        private int $cents,
    ) {
    }

    public static function fromInt(int $cents): self
    {
        if ($cents <= 0) {
            throw new InvalidAmountException();
        }

        return new self($cents);
    }

    public function toInt(): int
    {
        return $this->cents;
    }
}
