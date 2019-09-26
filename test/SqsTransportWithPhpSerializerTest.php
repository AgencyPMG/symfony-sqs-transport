<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test;

use Aws\Sqs\SqsClient;
use PMG\SqsTransport\SqsTransport;
use PMG\SqsTransport\SqsTransportConfig;

class SqsTransportWithPhpSerializerTest extends TransportTestCase
{
    protected function createTransport(SqsClient $client) : SqsTransport
    {
        return new SqsTransport($client, new SqsTransportConfig(
            self::getQueueUrl()
        ));
    }
}
