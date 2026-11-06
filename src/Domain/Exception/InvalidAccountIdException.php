<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidAccountIdException extends BaseException
{
    public function __construct()
    {
        parent::__construct('Invalid account id');
    }
}
