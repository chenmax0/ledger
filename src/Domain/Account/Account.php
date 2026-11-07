<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\Shared\Amount;
use App\Domain\Shared\Balance;

final class Account
{
    private function __construct(
        private readonly AccountId $id,
        private Balance $balance,
    ) {
    }

    public static function open(AccountId $id): self
    {
        return new self($id, Balance::zero());
    }

    public function getId(): AccountId
    {
        return $this->id;
    }

    public function getBalance(): Balance
    {
        return $this->balance;
    }

    public function credit(Amount $amount): void
    {
        $this->balance = $this->balance->credit($amount);
    }

    public function debit(Amount $amount): void
    {
        $this->balance = $this->balance->debit($amount);
    }
}
