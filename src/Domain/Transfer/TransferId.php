<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

use App\Domain\Exception\InvalidTransferIdException;

final readonly class TransferId
{
    private const UUID_V7_REGEX = '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';

    private function __construct(
        private string $id,
    ) {
    }

    public static function fromString(string $id): self
    {
        $lowercaseId = strtolower($id);

        if (1 !== preg_match(self::UUID_V7_REGEX, $lowercaseId)) {
            throw new InvalidTransferIdException();
        }

        return new self($lowercaseId);
    }

    public function toString(): string
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }
}
