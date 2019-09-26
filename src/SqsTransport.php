<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport;

use function ceil;
use function strlen;
use function strpos;
use function str_replace;
use function substr;
use Exception;
use Aws\Sqs\SqsClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use PMG\SqsTransport\Exception\SqsTransportException;
use PMG\SqsTransport\Stamp\SqsAttributeStamp;
use PMG\SqsTransport\Stamp\SqsReceiptHandleStamp;

/**
 * The SQS transport. Mean to work with non-fifo queues.
 *
 * @since 0.1
 */
final class SqsTransport implements TransportInterface
{
    private const HEADER_PREFIX = 'SfHeader.';

    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @var SqsTransportConfig
     */
    private $config;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        SqsClient $sqsClient,
        SqsTransportConfig $config,
        ?SerializerInterface $serializer=null
    ) {
        $this->sqsClient = $sqsClient;
        $this->config = $config;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function getConfig() : SqsTransportConfig
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope) : Envelope
    {
        // remove any message ids or receipt handles before encoding.
        $filteredEnvelope = $envelope
            ->withoutAll(TransportMessageIdStamp::class)
            ->withoutAll(SqsReceiptHandleStamp::class)
            ->withoutStampsOfType(SqsAttributeStamp::class)
            ;

        $encoded = $this->serializer->encode($filteredEnvelope);

        $sqsRequest = [
            'QueueUrl' => $this->config->getQueueUrl(),
            'MessageBody' => $encoded['body'],
        ];

        $attributes = $this->buildMessageAttributes($envelope, $encoded);
        if ($attributes) {
            $sqsRequest['MessageAttributes'] = $attributes;
        }

        $delayStamp = $filteredEnvelope->last(DelayStamp::class);
        if ($delayStamp instanceof DelayStamp) {
            // delay stamp is in milleseconds, SQS wants seconds
            $sqsRequest['DelaySeconds'] = ceil($delayStamp->getDelay() / 1000);
        }

        try {
            $response = $this->sqsClient->sendMessage($sqsRequest);
        } catch (Exception $e) {
            throw SqsTransportException::wrap($e);
        }

        return $filteredEnvelope->with(new TransportMessageIdStamp($response['MessageId']));
    }

    /**
     * {@inheritdoc}
     */
    public function get() : iterable
    {
        try {
            $response = $this->sqsClient->receiveMessage([
                'QueueUrl' => $this->config->getQueueUrl(),
                'MaxNumberOfMessages' => $this->config->getReceiveCount(),
                'WaitTimeSeconds' => $this->config->getReceiveWait(),
                'MessageAttributeNames' => ['.*'],
            ]);
        } catch (Exception $e) {
            throw SqsTransportException::wrap($e);
        }

        foreach ($response['Messages'] ?? [] as $message) {
            yield $this->sqsMessageToEnvelope($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope) : void
    {
        $receiptStamp = $envelope->last(SqsReceiptHandleStamp::class);
        if (!$receiptStamp instanceof SqsReceiptHandleStamp) {
            throw new LogicException(sprintf(
                'No %s stamp found on the Envelope',
                SqsReceiptHandleStamp::class
            ));
        }

        try {
            $this->sqsClient->deleteMessage([
                'QueueUrl' => $this->config->getQueueUrl(),
                'ReceiptHandle' => $receiptStamp->getReceiptHandle(),
            ]);
        } catch (Exception $e) {
            throw SqsTransportException::wrap($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope) : void
    {
        $this->ack($envelope);
    }

    private function sqsMessagetoEnvelope(array $sqsMessage) : Envelope
    {
        $envelope = $this->serializer->decode([
            'body' => $sqsMessage['Body'],
            'headers' => $this->extractHeadersFromAttributes($sqsMessage['MessageAttributes'] ?? []),
        ]);

        return $envelope->with(
            new TransportMessageIdStamp($sqsMessage['MessageId']),
            new SqsReceiptHandleStamp($sqsMessage['ReceiptHandle']),
            ...$this->extractStampsFromMessageAttributes($sqsMessage['MessageAttributes'] ?? [])
        );
    }

    private function buildMessageAttributes(Envelope $envelope, array $encoded) : array
    {
        $attributes = $this->buildHeaderMessageAttributes($encoded['headers'] ?? []);
        foreach (self::flatten($envelope->all()) as $stamp) {
            if (!$stamp instanceof SqsAttributeStamp) {
                continue;
            }
            $attributes[$stamp->getAttributeName()] = [
                'DataType' => $stamp->getAttributeDataType(),
                $stamp->getAttributeValueKey() => $stamp->getAttributeValue(),
            ];
        }

        return $attributes;
    }

    private function buildHeaderMessageAttributes(array $headers) : array
    {
        $attributes = [];
        foreach ($headers as $key => $value) {
            $attributes[self::headerNameToAttributeName($key)] = [
                'DataType' => 'String',
                'StringValue' => $value,
            ];
        }

        return $attributes;
    }

    private function extractStampsFromMessageAttributes(array $attributes) : iterable
    {
        foreach ($attributes as $key => $attribute) {
            if (self::isHeaderAttribute($key)) {
                continue;
            }

            yield SqsAttributeStamp::fromAttributeArray($key, $attribute);
        }
    }

    private function extractHeadersFromAttributes(array $attributes) : array
    {
        $headers = [];
        foreach ($attributes as $key => $attribute) {
            if (!self::isHeaderAttribute($key)) {
                continue;
            }

            $headers[self::attributeNameToHeaderName($key)] = $attribute['StringValue'];
        }

        return $headers;
    }

    /**
     * Can't have backslashes in an attribute name, which is what symfony does
     * for stamps in the `Serializer`.
     *
     * @see https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Messenger/Transport/Serialization/Serializer.php
     */
    private static function headerNameToAttributeName(string $headerName) : string
    {
        return self::HEADER_PREFIX.str_replace('\\', '__', $headerName);
    }

    /**
     * The reverse of `headerNameToAttributeName`
     *
     * @see https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Messenger/Transport/Serialization/Serializer.php
     */
    private static function attributeNameToHeaderName(string $attributeName) : string
    {
        return str_replace('__', '\\', substr($attributeName, strlen(self::HEADER_PREFIX)));
    }

    private static function isHeaderAttribute(string $attributeName) : bool
    {
        return 0 === strpos($attributeName, self::HEADER_PREFIX);
    }

    private static function flatten(iterable $iterables) : iterable
    {
        foreach ($iterables as $iterable) {
            if (is_iterable($iterable)) {
                yield from $iterable;
            } else {
                yield $iterable;
            }
        }
    }
}
