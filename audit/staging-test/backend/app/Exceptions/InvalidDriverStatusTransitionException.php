<?php

namespace App\Exceptions;

use App\Enums\DriverStatus;
use DomainException;

class InvalidDriverStatusTransitionException extends DomainException
{
    public function __construct(
        public readonly DriverStatus $from,
        public readonly DriverStatus $to,
        string $message = ''
    ) {
        $msg = $message ?: "لا يمكن تغيير حالة السائق من '{$from->label()}' إلى '{$to->label()}'.";
        parent::__construct($msg);
    }
}
