<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared;

use App\Domain\Exception\InvalidAmountException;
use App\Domain\Shared\Amount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
    #[DataProvider('validCents')]
    public function testValidAmountKeepsItsValue(int $cents): void
    {
        $amount = Amount::fromInt($cents);

        $this->assertSame($cents, $amount->toInt());
    }

    #[DataProvider('invalidCents')]
    public function testNonPositiveAmountIsRejected(int $cents): void
    {
        $this->expectException(InvalidAmountException::class);

        Amount::fromInt($cents);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function validCents(): array
    {
        return [
            'smallest valid amount' => [1],
            'ordinary amount' => [10],
            'largest valid amount' => [PHP_INT_MAX],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidCents(): array
    {
        return [
            'zero' => [0],
            'minus one' => [-1],
            'negative amount' => [-10],
            'smallest integer' => [PHP_INT_MIN],
        ];
    }
}
