<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Exception\SameAccountTransferException;
use App\Domain\Shared\Amount;

final readonly class Transfer
{
    private function __construct(
        private TransferId $id,
        private IdempotencyKey $idempotencyKey,
        private AccountId $sourceId,
        private AccountId $destinationId,
        private Amount $amount,
        private \DateTimeImmutable $date,
    ) {
    }

    public static function execute(
        TransferId $id,
        IdempotencyKey $idempotencyKey,
        Account $source,
        Account $destination,
        Amount $amount,
        \DateTimeImmutable $date,
    ): self {
        if ($source->getId()->equals($destination->getId())) {
            throw new SameAccountTransferException();
        }

        // Balance is immutable: computing both results first throws on overflow
        // before either account is mutated, so a failed transfer changes nothing.
        $source->getBalance()->debit($amount);
        $destination->getBalance()->credit($amount);

        $source->debit($amount);
        $destination->credit($amount);

        return new self($id, $idempotencyKey, $source->getId(), $destination->getId(), $amount, $date);
    }

    public function getId(): TransferId
    {
        return $this->id;
    }

    public function getIdempotencyKey(): IdempotencyKey
    {
        return $this->idempotencyKey;
    }

    public function getSourceId(): AccountId
    {
        return $this->sourceId;
    }

    public function getDestinationId(): AccountId
    {
        return $this->destinationId;
    }

    public function getAmount(): Amount
    {
        return $this->amount;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }
}
