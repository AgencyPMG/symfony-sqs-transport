<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test;

trait ValidDsnProvider
{
    public static function validDsn()
    {
        $dsn = TestCase::LOCALSTACK.'/queue/queue-name';

        yield 'sqs://' => ['sqs://'.$dsn, 'https://'.$dsn];

        yield 'sqs+http://' => ['sqs+http://'.$dsn, 'http://'.$dsn];

        yield 'sqs+https://' => ['sqs+https://'.$dsn, 'https://'.$dsn];
    }

}
