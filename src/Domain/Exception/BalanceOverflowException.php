<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class BalanceOverflowException extends BaseException
{
    public function __construct()
    {
        parent::__construct('Balance overflow');
    }
}
