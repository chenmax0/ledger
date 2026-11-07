<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Transfer;

use App\Domain\Exception\InvalidTransferIdException;
use App\Domain\Transfer\TransferId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransferIdTest extends TestCase
{
    private const VALID_ID = '01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0f';
    private const OTHER_VALID_ID = '01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e01';

    public function testValidUuidV7KeepsItsValue(): void
    {
        $id = TransferId::fromString(self::VALID_ID);

        $this->assertSame(self::VALID_ID, $id->toString());
    }

    public function testUppercaseIsNormalisedToLowercase(): void
    {
        $id = TransferId::fromString(strtoupper(self::VALID_ID));

        $this->assertSame(self::VALID_ID, $id->toString());
    }

    #[DataProvider('invalidIds')]
    public function testInvalidIdIsRejected(string $invalidId): void
    {
        $this->expectException(InvalidTransferIdException::class);

        TransferId::fromString($invalidId);
    }

    public function testSameValueIsEqual(): void
    {
        $id = TransferId::fromString(self::VALID_ID);
        $sameId = TransferId::fromString(self::VALID_ID);

        $this->assertTrue($id->equals($sameId));
    }

    public function testSameValueWithDifferentCaseIsEqual(): void
    {
        $lowercaseId = TransferId::fromString(self::VALID_ID);
        $uppercaseId = TransferId::fromString(strtoupper(self::VALID_ID));

        $this->assertTrue($lowercaseId->equals($uppercaseId));
    }

    public function testDifferentValuesAreNotEqual(): void
    {
        $id = TransferId::fromString(self::VALID_ID);
        $otherId = TransferId::fromString(self::OTHER_VALID_ID);

        $this->assertFalse($id->equals($otherId));
        $this->assertFalse($otherId->equals($id));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIds(): array
    {
        return [
            'empty string' => [''],
            'random text' => ['not a uuid'],
            'uuid v1' => ['123e4567-e89b-12d3-a456-426655440000'],
            'uuid v4' => ['550e8400-e29b-41d4-a716-446655440000'],
            'invalid variant' => ['01936f8e-6b2a-7c3d-ce4f-5a6b7c8d9e0f'],
            'non hexadecimal character' => ['01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0g'],
            'missing hyphens' => ['01936f8e6b2a7c3d9e4f5a6b7c8d9e0f'],
            'too short' => ['01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0'],
            'too long' => ['01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0f0'],
            'trailing newline' => ["01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0f\n"],
            'surrounding spaces' => [' 01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0f '],
            'braces' => ['{01936f8e-6b2a-7c3d-9e4f-5a6b7c8d9e0f}'],
            'base58 format' => ['1BKocMc5BnrVcuq2ti4Eqm'],
            '16 raw bytes' => ['0000000000000000'],
        ];
    }
}
