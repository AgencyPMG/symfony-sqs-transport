<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Exception;

use Exception;
use Symfony\Component\Messenger\Exception\TransportException;

final class SqsTransportException extends TransportException
{
    public static function wrap(Exception $prev) : self
    {
        return new static($prev->getMessage(), $prev->getCode(), $prev);
    }
}
