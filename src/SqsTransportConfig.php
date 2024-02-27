<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport;

use function intval;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * Container for the various options which can be set for the SQS transport.
 *
 * @since 0.1
 */
final class SqsTransportConfig
{
    const DEFAULT_RECEIVE_COUNT = 1;
    const DEFAULT_RECEIVE_WAIT = 0;

    /**
     * The Queue URL on which the transport will act.
     *
     * @var string
     */
    private $queueUrl;

    /**
     * The `MaxNumberOfMessages` passed to SQS's `ReceiveMessage` action.
     *
     * @var int
     */
    private $receiveCount;

    /**
     * The `WaitTimeSeconds` passed to SQS's `ReceiveMessage` action.
     *
     * @var int
     */
    private $receiveWait;

    public function __construct(
        string $queueUrl,
        ?int $receiveCount=null,
        ?int $receiveWait=null
    ) {
        trigger_deprecation('PMG/SqsTransport', '0', 'This class is deprecated. Use Alli\Platform\Bundle\SqsTransport\SqsTransportConfig instead.');
        $this->queueUrl = $queueUrl;
        $this->receiveCount = self::validateReceiveCount($receiveCount ?? self::DEFAULT_RECEIVE_COUNT);
        $this->receiveWait = self::validateReceiveWait($receiveWait ?? self::DEFAULT_RECEIVE_WAIT);
    }

    public static function fromArray(array $in) : self
    {
        if (!isset($in['queue_url'])) {
            throw new InvalidArgumentException('`queue_url` is not set');
        }

        return new self(
            $in['queue_url'],
            isset($in['receive_count']) ? intval($in['receive_count']) : null,
            isset($in['receive_wait']) ? intval($in['receive_wait']) : null
        );
    }

    public static function validateReceiveCount(int $receiveCount) : int
    {
        if ($receiveCount < 1 || $receiveCount > 10) {
            throw new InvalidArgumentException('$receiveCount must be between 1 and 10 (inclusive)');
        }

        return $receiveCount;
    }

    public static function validateReceiveWait(int $receiveWait) : int
    {
        if ($receiveWait < 0) {
            throw new InvalidArgumentException('$receiveWait must be a positive integer');
        }

        return $receiveWait;
    }

    public function getQueueUrl() : string
    {
        return $this->queueUrl;
    }

    public function getReceiveCount() : int
    {
        return $this->receiveCount;
    }

    public function getReceiveWait() : int
    {
        return $this->receiveWait;
    }
}
