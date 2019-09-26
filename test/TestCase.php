<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test;

use Aws\Sqs\SqsClient;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;
use PMG\SqsTransport\Test\Fixtures\TestMessage;

abstract class TestCase extends PHPUnitTestCase
{
    const DEFAULT_ENDPOINT = 'http://localhost:4576';

    protected static function createSqsClient() : SqsClient
    {
        return new SqsClient([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => getenv('SQS_ENDPOINT') ?: self::DEFAULT_ENDPOINT,
            'credentials' => [
                'key' => 'localstack_ignores_this',
                'secret' => 'localstack_ignores_this',
            ],
        ]);
    }

    protected static function createEnvelope(string $messageId, StampInterface ...$stamps) : Envelope
    {
        return new Envelope(new TestMessage(static::class.'::'.$messageId), $stamps);
    }
}
