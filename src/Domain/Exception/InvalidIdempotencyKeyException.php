<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidIdempotencyKeyException extends BaseException
{
    public function __construct()
    {
        parent::__construct('Invalid idempotency key');
    }
}
