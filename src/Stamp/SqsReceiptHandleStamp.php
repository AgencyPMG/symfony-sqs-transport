<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class SqsReceiptHandleStamp implements StampInterface
{
    /**
     * @var string
     */
    private $receiptHandle;

    public function __construct(string $receiptHandle)
    {
        $this->receiptHandle = $receiptHandle;
    }

    public function getReceiptHandle() : string
    {
        return $this->receiptHandle;
    }
}
