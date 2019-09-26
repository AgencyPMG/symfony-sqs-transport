<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test;

use Symfony\Component\Messenger\Exception\TransportException;
use PMG\SqsTransport\SqsTransport;
use PMG\SqsTransport\SqsTransportConfig;
use PMG\SqsTransport\Stamp\SqsReceiptHandleStamp;

class UnhappySqsTransportTest extends TestCase
{
    private $transport;

    public function testGetFailsWithTransportExceptionWhenSqsClientErrors()
    {
        $this->expectException(TransportException::class);

        iterator_to_array($this->transport->get());
    }

    public function testSendFailsWithTransportExceptionWhenSqsClientErrors()
    {
        $this->expectException(TransportException::class);

        $this->transport->send(self::createEnvelope(__FUNCTION__));
    }

    public function testAckFailsWithTransportExceptionWhenSqsClientErrors()
    {
        $this->expectException(TransportException::class);

        $this->transport->ack(self::createEnvelope(
            __FUNCTION__,
            new SqsReceiptHandleStamp(__FUNCTION__)
        ));
    }

    public function testRejectFailsWithTransportExceptionWhenSqsClientErrors()
    {
        $this->expectException(TransportException::class);

        $this->transport->reject(self::createEnvelope(
            __FUNCTION__,
            new SqsReceiptHandleStamp(__FUNCTION__)
        ));
    }

    protected function setUp() : void
    {
        // use an invalid queue urls to force things to fail
        $this->transport = new SqsTransport(self::createSqsClient(), new SqsTransportConfig(
            'http://localhost:4576/queue/invalid-queue-does-not-exist-at-all'
        ));
    }
}
