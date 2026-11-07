<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Transfer;

use App\Domain\Exception\InvalidIdempotencyKeyException;
use App\Domain\Transfer\IdempotencyKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdempotencyKeyTest extends TestCase
{
    private const VALID_KEY = '01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e0f';
    private const OTHER_VALID_KEY = '01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e01';

    #[DataProvider('validKeys')]
    public function testValidUuidKeepsItsValue(string $validKey): void
    {
        $key = IdempotencyKey::fromString($validKey);

        $this->assertSame($validKey, $key->toString());
    }

    public function testUppercaseIsNormalisedToLowercase(): void
    {
        $key = IdempotencyKey::fromString(strtoupper(self::VALID_KEY));

        $this->assertSame(self::VALID_KEY, $key->toString());
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidKeyIsRejected(string $invalidKey): void
    {
        $this->expectException(InvalidIdempotencyKeyException::class);

        IdempotencyKey::fromString($invalidKey);
    }

    public function testSameValueIsEqual(): void
    {
        $key = IdempotencyKey::fromString(self::VALID_KEY);
        $sameKey = IdempotencyKey::fromString(self::VALID_KEY);

        $this->assertTrue($key->equals($sameKey));
    }

    public function testSameValueWithDifferentCaseIsEqual(): void
    {
        $lowercaseKey = IdempotencyKey::fromString(self::VALID_KEY);
        $uppercaseKey = IdempotencyKey::fromString(strtoupper(self::VALID_KEY));

        $this->assertTrue($lowercaseKey->equals($uppercaseKey));
    }

    public function testDifferentValuesAreNotEqual(): void
    {
        $key = IdempotencyKey::fromString(self::VALID_KEY);
        $otherKey = IdempotencyKey::fromString(self::OTHER_VALID_KEY);

        $this->assertFalse($key->equals($otherKey));
        $this->assertFalse($otherKey->equals($key));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validKeys(): array
    {
        return [
            'version 1 (lower bound)' => ['01936f8e-6b2a-1c3d-9e4f-5a6b7c8d9e0f'],
            'version 4 (most common)' => ['01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e0f'],
            'version 7' => ['01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0f'],
            'version 8 (upper bound)' => ['01936f8e-6b2a-8c3d-9e4f-5a6b7c8d9e0f'],
            'variant 8 (lower bound)' => ['01936f8e-6b2a-4c3d-8e4f-5a6b7c8d9e0f'],
            'variant b (upper bound)' => ['01936f8e-6b2a-4c3d-be4f-5a6b7c8d9e0f'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'version 0 (below lower bound)' => ['01936f8e-6b2a-0c3d-9e4f-5a6b7c8d9e0f'],
            'version 9 (above upper bound)' => ['01936f8e-6b2a-9c3d-9e4f-5a6b7c8d9e0f'],
            'variant 7 (below lower bound)' => ['01936f8e-6b2a-4c3d-7e4f-5a6b7c8d9e0f'],
            'variant c (above upper bound)' => ['01936f8e-6b2a-4c3d-ce4f-5a6b7c8d9e0f'],
            'nil uuid' => ['00000000-0000-0000-0000-000000000000'],
            'max uuid' => ['ffffffff-ffff-ffff-ffff-ffffffffffff'],
            'empty string' => [''],
            'random text' => ['not a uuid'],
            'non hexadecimal character' => ['01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e0g'],
            'missing hyphens' => ['01936f8e6b2a4c3d9e4f5a6b7c8d9e0f'],
            'too short' => ['01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e0'],
            'too long' => ['01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e0f0'],
            'trailing newline' => ["01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e0f\n"],
            'surrounding spaces' => [' 01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e0f '],
            'braces' => ['{01936f8e-6b2a-4c3d-9e4f-5a6b7c8d9e0f}'],
            'base58 format' => ['1BKocMc5BnrVcuq2ti4Eqm'],
            '16 raw bytes' => ['0000000000000000'],
        ];
    }
}
