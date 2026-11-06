<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Account;

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Exception\BalanceOverflowException;
use App\Domain\Shared\Amount;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    private const VALID_ACCOUNT_ID = '01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0f';

    public function testNewAccountHasGivenId(): void
    {
        $id = AccountId::fromString(self::VALID_ACCOUNT_ID);
        $account = Account::open($id);
        $this->assertTrue($id->equals($account->getId()));
    }

    public function testNewAccountHasZeroBalance(): void
    {
        $id = AccountId::fromString(self::VALID_ACCOUNT_ID);
        $account = Account::open($id);
        $this->assertSame(0, $account->getBalance()->toInt());
    }

    public function testAccountCanBeCredited(): void
    {
        $id = AccountId::fromString(self::VALID_ACCOUNT_ID);
        $account = Account::open($id);
        $account->credit(Amount::fromInt(100));
        $this->assertSame(100, $account->getBalance()->toInt());
    }

    public function testAccountCanBeDebited(): void
    {
        $id = AccountId::fromString(self::VALID_ACCOUNT_ID);
        $account = Account::open($id);
        $account->debit(Amount::fromInt(100));
        $this->assertSame(-100, $account->getBalance()->toInt());
    }

    public function testAccountBalanceCannotOverflow(): void
    {
        $id = AccountId::fromString(self::VALID_ACCOUNT_ID);
        $account = Account::open($id);
        $account->credit(Amount::fromInt(PHP_INT_MAX));

        try {
            $account->credit(Amount::fromInt(1));
            $this->fail('BalanceOverflowException was not thrown');
        } catch (BalanceOverflowException) {
            $this->assertSame(PHP_INT_MAX, $account->getBalance()->toInt());
        }
    }
}
