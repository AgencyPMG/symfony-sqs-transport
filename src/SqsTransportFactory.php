<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport;

use function explode;
use function ltrim;
use function parse_url;
use function sprintf;
use const PHP_URL_SCHEME;
use Aws\Sqs\SqsClient;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class SqsTransportFactory implements TransportFactoryInterface
{
    /**
     * @var Aws\Sqs\SqsClient
     */
    private $sqsClient;

    public function __construct(SqsClient $sqsClient)
    {
        $this->sqsClient = $sqsClient;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer) : TransportInterface
    {
        $url = parse_url($dsn);
        if (empty($url['host'])) {
            throw new InvalidArgumentException('$dsn does not have a host name, example: sqs://queue.amazonaws.com/80398EXAMPLE/MyQueue');
        }
        if (empty($url['path'])) {
            throw new InvalidArgumentException('$dsn does not have a path, example: sqs://queue.amazonaws.com/80398EXAMPLE/MyQueue');
        }

        parse_str($url['query'] ?? '', $query);

        $finalConfig = array_replace($query, $options, [
            'queue_url' => self::buildQueueUrl($url),
        ]);

        return new SqsTransport(
            $this->sqsClient,
            SqsTransportConfig::fromArray($finalConfig),
            $serializer
        );
    }

    public function supports(string $dsn, array $options) : bool
    {
        $scheme = parse_url($dsn, PHP_URL_SCHEME);

        return $scheme && in_array(strtolower($scheme), ['sqs', 'sqs+http', 'sqs+https'], true);
    }

    private static function buildQueueUrl(array $url) : string
    {
        $schemeParts = explode('+', $url['scheme'], 2);
        $queueUrlScheme = count($schemeParts) > 1 ? $schemeParts[1] : 'https';

        if (isset($url['port'])) {
            return sprintf(
                '%s://%s:%s/%s',
                $queueUrlScheme,
                $url['host'],
                $url['port'],
                ltrim($url['path'], '/')
            );
        }

        return sprintf(
            '%s://%s/%s',
            $queueUrlScheme,
            $url['host'],
            ltrim($url['path'], '/')
        );
    }
}
