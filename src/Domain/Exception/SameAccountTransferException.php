<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class SameAccountTransferException extends BaseException
{
    public function __construct()
    {
        parent::__construct('Cannot transfer to same account');
    }
}
