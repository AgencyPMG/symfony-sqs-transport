<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test;

use Aws\Sqs\SqsClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use PMG\SqsTransport\SqsTransport;
use PMG\SqsTransport\Stamp\SqsAttributeStamp;
use PMG\SqsTransport\Stamp\SqsReceiptHandleStamp;
use PMG\SqsTransport\Stamp\SqsStringAttributeStamp;
use PMG\SqsTransport\Stamp\SqsNumberAttributeStamp;
use PMG\SqsTransport\Test\Fixtures\TestMessage;

abstract class TransportTestCase extends TestCase
{
    const QUEUE_NAME = 'pmg-sqs-transport-test';

    private static $sqsClient;

    private static $queueUrl;

    protected $transport;

    public function testSentEnvelopsAreReturnedWithMessageIdStamp()
    {
        $envelope = $this->transport->send(self::createEnvelope(__FUNCTION__));

        $this->assertNotNull($envelope->last(TransportMessageIdStamp::class));
    }

    public function testMessageIdStampsAreRemovedFromSentEnvelopes()
    {
        $envelope = $this->transport->send(self::createEnvelope(
            __FUNCTION__,
            new TransportMessageIdStamp('123')
        ));

        $stamps = $envelope->all(TransportMessageIdStamp::class);
        $this->assertCount(1, $stamps, 'should have removed previous message id stamps');
    }

    public function testReceiptHandleStampsAreRemovedFromSEntEnvelopes()
    {
        $envelope = $this->transport->send(self::createEnvelope(
            __FUNCTION__,
            new SqsReceiptHandleStamp('123')
        ));

        $stamps = $envelope->all(SqsReceiptHandleStamp::class);
        $this->assertCount(0, $stamps, 'should have removed all receipt handle stamps');
    }

    public function testMessagesWithDelayStampsAreNotAvaialbleImmediately()
    {
        $this->transport->send(self::createEnvelope(
            __FUNCTION__,
            new DelayStamp(10000)
        ));

        $envelopes = iterator_to_array($this->transport->get());

        $this->assertCount(0, $envelopes, 'delayed messages should not be availabe');
    }

    public function testAckingEnvelopsWithOutAnSqsReceiptHandleStampCAusesAnError()
    {
        $this->expectException(LogicException::class);

        $this->transport->ack(self::createEnvelope(__FUNCTION__));
    }

    public function testReceivedMessagesIncludeASqsReceiptHandleStamp()
    {
        $this->transport->send(self::createEnvelope(__FUNCTION__));

        $received = iterator_to_array($this->transport->get());

        $this->assertCount(1, $received);
        $this->assertNotNull($received[0]->last(SqsReceiptHandleStamp::class));

        $this->transport->reject($received[0]);
    }

    public function testMessagesCanBeSentReceivedAndAckedWithCustomStamps()
    {
        $this->transport->send(self::createEnvelope(
            __FUNCTION__,
            new BusNameStamp(__FUNCTION__)
        ));

        $received = iterator_to_array($this->transport->get());

        $this->assertCount(1, $received);
        $this->assertEquals(new BusNameStamp(__FUNCTION__), $received[0]->last(BusNameStamp::class));

        $this->transport->reject($received[0]);
    }

    public function testMessagesAreSerializedCorrectly()
    {
        $originalEnvelope = self::createEnvelope(__FUNCTION__);

        $this->transport->send($originalEnvelope);

        $received = iterator_to_array($this->transport->get());
    
        $this->assertCount(1, $received);
        $this->assertEquals($originalEnvelope->getMessage(), $received[0]->getMessage());

        $this->transport->ack($received[0]);
    }

    public static function sqsAttributeStamps()
    {
        yield SqsStringAttributeStamp::class => [
            new SqsStringAttributeStamp('attributeNameHere', 'value'),
        ];

        yield SqsNumberAttributeStamp::class => [
            new SqsNumberAttributeStamp('attributeNameHere', 123),
        ];
    }

    /**
     * @dataProvider sqsAttributeStamps
     */
    public function testCustomMessageAttributesCanBeSentWithSqsAttributeStamp(SqsAttributeStamp $stamp)
    {
        $this->transport->send(self::createEnvelope(__FUNCTION__, $stamp));

        $received = iterator_to_array($this->transport->get());

        $this->assertCount(1, $received);
        $stamps = $received[0]->all(get_class($stamp));
        $this->assertCount(1, $stamps, 'transport should remove attribute stamps so they are not duplicated');
        $this->assertEquals($stamp, $stamps[0]);
    }

    public static function setupBeforeClass() : void
    {
        self::$sqsClient = self::createSqsClient();

        $queues = self::$sqsClient->listQueues([
            'QueueNamePrefix' => self::getQueueName(),
        ]);

        if (empty($queues['QueueUrls'])) {
            $response = self::$sqsClient->createQueue([
                'QueueName' => self::getQueueName(),
            ]);
            self::$queueUrl = $response['QueueUrl'];
        } else {
            self::$queueUrl = $queues['QueueUrls'][0];
        }
    }

    protected function setUp() : void
    {
        self::$sqsClient->purgeQueue([
            'QueueUrl' => self::getQueueUrl(),
        ]);
        $this->transport = $this->createTransport(self::$sqsClient);
    }

    abstract protected function createTransport(SqsClient $client) : SqsTransport;

    protected static function getQueueName() : string
    {
        return 'pmg-sqs-transport-test';
    }

    protected static function getQueueUrl() : string
    {
        if (!self::$queueUrl) {
            throw new \LogicException('$queueUrl is not set!');
        }

        return self::$queueUrl;
    }
}
