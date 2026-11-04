<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Exception\BalanceOverflowException;
use App\Domain\Shared\Amount;
use App\Domain\Shared\Balance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BalanceTest extends TestCase
{
    public function testNewBalanceIsZero(): void
    {
        $balance = Balance::zero();

        $this->assertSame(0, $balance->toInt());
    }

    #[DataProvider('storedCents')]
    public function testBalanceCanBeRestoredFromAnySignedInteger(int $cents): void
    {
        $balance = Balance::fromInt($cents);

        $this->assertSame($cents, $balance->toInt());
    }

    #[DataProvider('credits')]
    public function testCreditIncreasesBalanceByExactAmount(int $initial, int $amount, int $expected): void
    {
        $balance = Balance::fromInt($initial);

        $credited = $balance->credit(Amount::fromInt($amount));

        $this->assertSame($expected, $credited->toInt());
    }

    #[DataProvider('debits')]
    public function testDebitDecreasesBalanceByExactAmount(int $initial, int $amount, int $expected): void
    {
        $balance = Balance::fromInt($initial);

        $debited = $balance->debit(Amount::fromInt($amount));

        $this->assertSame($expected, $debited->toInt());
    }

    public function testCreditDoesNotModifyOriginalBalance(): void
    {
        $balance = Balance::fromInt(100);

        $balance->credit(Amount::fromInt(50));

        $this->assertSame(100, $balance->toInt());
    }

    public function testDebitDoesNotModifyOriginalBalance(): void
    {
        $balance = Balance::fromInt(100);

        $balance->debit(Amount::fromInt(50));

        $this->assertSame(100, $balance->toInt());
    }

    public function testCreditBeyondMaximumIsRejected(): void
    {
        $balance = Balance::fromInt(PHP_INT_MAX);

        $this->expectException(BalanceOverflowException::class);

        $balance->credit(Amount::fromInt(1));
    }

    public function testDebitBeyondMinimumIsRejected(): void
    {
        $balance = Balance::fromInt(PHP_INT_MIN);

        $this->expectException(BalanceOverflowException::class);

        $balance->debit(Amount::fromInt(1));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function storedCents(): array
    {
        return [
            'zero' => [0],
            'positive' => [100],
            'negative (overdraft allowed)' => [-100],
            'largest integer' => [PHP_INT_MAX],
            'smallest integer' => [PHP_INT_MIN],
        ];
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function credits(): array
    {
        return [
            'positive balance' => [100, 50, 150],
            'negative balance stays negative' => [-100, 30, -70],
            'negative balance becomes positive' => [-100, 150, 50],
            'reaches the maximum exactly' => [PHP_INT_MAX - 1, 1, PHP_INT_MAX],
        ];
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function debits(): array
    {
        return [
            'positive balance stays positive' => [100, 30, 70],
            'positive balance becomes negative' => [100, 150, -50],
            'negative balance goes further down' => [-100, 50, -150],
            'reaches the minimum exactly' => [PHP_INT_MIN + 1, 1, PHP_INT_MIN],
        ];
    }
}
