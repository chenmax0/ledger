<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Transfer;

use App\Domain\Account\Account;
use App\Domain\Account\AccountId;
use App\Domain\Exception\BalanceOverflowException;
use App\Domain\Exception\SameAccountTransferException;
use App\Domain\Shared\Amount;
use App\Domain\Transfer\IdempotencyKey;
use App\Domain\Transfer\Transfer;
use App\Domain\Transfer\TransferId;
use PHPUnit\Framework\TestCase;

final class TransferTest extends TestCase
{
    private const TRANSFER_ID = '01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e02';
    private const SOURCE_ID = '01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0f';
    private const DESTINATION_ID = '01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e01';
    private const IDEMPOTENCY_KEY = '550e8400-e29b-41d4-a716-446655440000';
    private const DATE = '2026-11-08 10:00:00';

    public function testExecuteMovesExactAmountBetweenAccounts(): void
    {
        $source = $this->accountWithBalance(self::SOURCE_ID, 500);
        $destination = $this->accountWithBalance(self::DESTINATION_ID, 50);

        $this->execute($source, $destination, 200);

        $this->assertSame(300, $source->getBalance()->toInt());
        $this->assertSame(250, $destination->getBalance()->toInt());
    }

    public function testExecuteAllowsSourceToGoIntoOverdraft(): void
    {
        $source = $this->accountWithBalance(self::SOURCE_ID, 100);
        $destination = $this->accountWithBalance(self::DESTINATION_ID, 0);

        $this->execute($source, $destination, 150);

        $this->assertSame(-50, $source->getBalance()->toInt());
        $this->assertSame(150, $destination->getBalance()->toInt());
    }

    public function testTransferKeepsItsData(): void
    {
        $source = $this->accountWithBalance(self::SOURCE_ID, 500);
        $destination = $this->accountWithBalance(self::DESTINATION_ID, 50);

        $transfer = $this->execute($source, $destination, 200);

        $this->assertTrue(TransferId::fromString(self::TRANSFER_ID)->equals($transfer->getId()));
        $this->assertTrue(IdempotencyKey::fromString(self::IDEMPOTENCY_KEY)->equals($transfer->getIdempotencyKey()));
        $this->assertTrue($source->getId()->equals($transfer->getSourceId()));
        $this->assertTrue($destination->getId()->equals($transfer->getDestinationId()));
        $this->assertSame(200, $transfer->getAmount()->toInt());
        $this->assertEquals(new \DateTimeImmutable(self::DATE), $transfer->getDate());
    }

    public function testTransferToSameAccountIsRejectedWithoutChangingBalance(): void
    {
        $account = $this->accountWithBalance(self::SOURCE_ID, 500);

        try {
            $this->execute($account, $account, 200);
            $this->fail('SameAccountTransferException was not thrown');
        } catch (SameAccountTransferException) {
            $this->assertSame(500, $account->getBalance()->toInt());
        }
    }

    public function testTransferBetweenTwoInstancesOfSameAccountIsRejected(): void
    {
        $source = $this->accountWithBalance(self::SOURCE_ID, 500);
        $sameAccountLoadedTwice = $this->accountWithBalance(self::SOURCE_ID, 500);

        $this->expectException(SameAccountTransferException::class);

        $this->execute($source, $sameAccountLoadedTwice, 200);
    }

    public function testDestinationOverflowLeavesBothAccountsUnchanged(): void
    {
        $source = $this->accountWithBalance(self::SOURCE_ID, 500);
        $destination = $this->accountWithBalance(self::DESTINATION_ID, PHP_INT_MAX);

        try {
            $this->execute($source, $destination, 1);
            $this->fail('BalanceOverflowException was not thrown');
        } catch (BalanceOverflowException) {
            $this->assertSame(500, $source->getBalance()->toInt());
            $this->assertSame(PHP_INT_MAX, $destination->getBalance()->toInt());
        }
    }

    private function execute(Account $source, Account $destination, int $cents): Transfer
    {
        return Transfer::execute(
            TransferId::fromString(self::TRANSFER_ID),
            IdempotencyKey::fromString(self::IDEMPOTENCY_KEY),
            $source,
            $destination,
            Amount::fromInt($cents),
            new \DateTimeImmutable(self::DATE),
        );
    }

    private function accountWithBalance(string $id, int $cents): Account
    {
        $account = Account::open(AccountId::fromString($id));

        if ($cents > 0) {
            $account->credit(Amount::fromInt($cents));
        }

        return $account;
    }
}
