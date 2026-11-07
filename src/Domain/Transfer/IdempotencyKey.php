<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

use App\Domain\Exception\InvalidIdempotencyKeyException;

final readonly class IdempotencyKey
{
    private const UUID_REGEX = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $loweredValue = strtolower($value);

        if (1 !== preg_match(self::UUID_REGEX, $loweredValue)) {
            throw new InvalidIdempotencyKeyException();
        }

        return new self($loweredValue);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
