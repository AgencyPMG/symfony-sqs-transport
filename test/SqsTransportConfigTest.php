<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use PMG\SqsTransport\SqsTransportConfig as Config;

class SqsTransportConfigTest extends TestCase
{
    const URL = 'http://example.com/queue/here';

    public function testConfigCannotBeCreateFromAnArrayWithMissingQueueUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('queue_url');

        Config::fromArray([]);
    }

    public function testConfigCanBeCreatedWithDefaultValues()
    {
        $config = Config::fromArray([
            'queue_url' => self::URL,
        ]);

        $this->assertSame(self::URL, $config->getQueueUrl());
        $this->assertSame(Config::DEFAULT_RECEIVE_COUNT, $config->getReceiveCount());
        $this->assertSame(Config::DEFAULT_RECEIVE_WAIT, $config->getReceiveWait());
    }

    public static function badReceiveCount()
    {
        yield 'negative' => [-1];
        yield 'more than 10' => [11];
    }

    /**
     * @dataProvider badReceiveCount
     */
    public function testConfigCannotBeCreatedWithInvalidReceiveCount(int $count)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('receiveCount');

        Config::fromArray([
            'queue_url' => self::URL,
            'receive_count' => $count,
        ]);
    }

    public static function badReceiveWait()
    {
        yield 'negative' => [-1];
    }

    /**
     * @dataProvider badReceiveWait
     */
    public function testConfigCannotBeCreatedWithInvalidReceivedWait(int $wait)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('receiveWait');

        Config::fromArray([
            'queue_url' => self::URL,
            'receive_wait' => $wait,
        ]);
    }
}
