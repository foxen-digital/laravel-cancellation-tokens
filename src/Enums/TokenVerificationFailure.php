<?php

namespace Foxen\CancellationToken\Enums;

enum TokenVerificationFailure: string
{
    case NotFound = 'not_found';
    case Expired = 'expired';
    case Consumed = 'consumed';
}
