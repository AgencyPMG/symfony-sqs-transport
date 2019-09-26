<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test;

use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use PMG\SqsTransport\SqsTransportFactory;

class SqsTransportFactoryTest extends TestCase
{
    const LOCALSTACK = 'localhost:4576/queue/this-does-not-exist';

    private $factory, $serializer;

    public static function validDsn()
    {
        yield 'sqs://' => ['sqs://'.self::LOCALSTACK, 'https://'.self::LOCALSTACK];

        yield 'sqs+http://' => ['sqs+http://'.self::LOCALSTACK, 'http://'.self::LOCALSTACK];

        yield 'sqs+https://' => ['sqs+https://'.self::LOCALSTACK, 'https://'.self::LOCALSTACK];
    }

    /**
     * @dataProvider validDsn
     */
    public function testValidDsnAreSupported(string $dsn)
    {
        $supports = $this->factory->supports($dsn, []);

        $this->assertTrue($supports);
    }

    public function testNonSqsDnsAreNotSupported()
    {
        $supports = $this->factory->supports('http://example.com', []);

        $this->assertFalse($supports);
    }

    /**
     * @dataProvider validDsn
     */
    public function testCreateTransportCreatesATransportWithTheExpectedQueueUrl(string $dsn, string $queueUrl)
    {
        $transport = $this->factory->createTransport($dsn, [], $this->serializer);

        $this->assertSame($queueUrl, $transport->getConfig()->getQueueUrl());
    }

    /**
     * @dataProvider validDsn
     */
    public function testCreateTransportUsesValuesFromTheDsnQuery(string $dsn)
    {
        $transport = $this->factory->createTransport(
            $dsn.'?receive_count=5&receive_wait=10',
            [],
            $this->serializer
        );

        $this->assertEquals(5, $transport->getConfig()->getReceiveCount());
        $this->assertEquals(10, $transport->getConfig()->getReceiveWait());
    }

    /**
     * @dataProvider validDsn
     */
    public function testCreateTransportFavorsValuesFromOptionsOverQuery(string $dsn)
    {
        $transport = $this->factory->createTransport(
            $dsn.'?receive_count=5&receive_wait=10',
            [
                'receive_count' => 10,
                'receive_wait' => 1,
            ],
            $this->serializer
        );

        $this->assertEquals(10, $transport->getConfig()->getReceiveCount());
        $this->assertEquals(1, $transport->getConfig()->getReceiveWait());
    }

    protected function setUp() : void
    {
        $this->factory = new SqsTransportFactory(self::createSqsClient());
        $this->serializer = new PhpSerializer();
    }
}
