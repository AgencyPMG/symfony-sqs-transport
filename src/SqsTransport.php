<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport;

use function ceil;
use function json_decode;
use function json_encode;
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
    private const HEADER_ATTRIBUTE = 'Symfony.Messenger.SerializerHeaders';

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
        trigger_deprecation('PMG/SqsTransport', '0', 'This class is deprecated. Use Alli\Platform\Bundle\SqsTransport\SqsTransport instead.');
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
            ->withoutAll(DelayStamp::class)
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

        $delayStamp = $envelope->last(DelayStamp::class);
        if ($delayStamp instanceof DelayStamp) {
            // delay stamp is in milleseconds, SQS wants seconds
            $sqsRequest['DelaySeconds'] = intval(ceil($delayStamp->getDelay() / 1000));
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
                'MessageAttributeNames' => ['All'],
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
            // these two keys, `body` and `headers` play nice with the built in
            // implementations of `SerializerInterface`
            'body' => $sqsMessage['Body'],
            'headers' => $this->extractHeadersFromAttributes($sqsMessage['MessageAttributes'] ?? []),
            // but incase one wants to write their own, custom serializer
            // we provide the entire, raw SQS message as well.
            'aws_sqs_message' => $sqsMessage,
        ]);

        return $envelope->with(
            new TransportMessageIdStamp($sqsMessage['MessageId']),
            new SqsReceiptHandleStamp($sqsMessage['ReceiptHandle']),
            ...$this->extractStampsFromMessageAttributes($sqsMessage['MessageAttributes'] ?? [])
        );
    }

    private function buildMessageAttributes(Envelope $envelope, array $encoded) : array
    {
        $attributes = [];

        $headers = $encoded['headers'] ?? null;
        if ($headers) {
            // this will `double_encode` any json that happens to be in the
            // headers array. #yolo
            $attributes[self::HEADER_ATTRIBUTE] = [
                'DataType' => 'String',
                'StringValue' => json_encode($headers),
            ];
        }

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

    private function extractStampsFromMessageAttributes(array $attributes) : iterable
    {
        foreach ($attributes as $key => $attribute) {
            if (self::HEADER_ATTRIBUTE === $key) {
                continue;
            }

            yield SqsAttributeStamp::fromAttributeArray($key, $attribute);
        }
    }

    private function extractHeadersFromAttributes(array $attributes) : array
    {
        if (isset($attributes[self::HEADER_ATTRIBUTE])) {
            return json_decode($attributes[self::HEADER_ATTRIBUTE]['StringValue'], true);
        }

        return [];
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
