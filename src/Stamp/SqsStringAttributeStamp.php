<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Stamp;

final class SqsStringAttributeStamp extends SqsAttributeStamp
{
    /**
     * @var string
     */
    private $value;

    public function __construct(string $name, string $value)
    {
        parent::__construct($name);
        $this->value = $value;
    }

    public function getAttributeValue() : string
    {
        return $this->value;
    }

    public function getAttributeDataType() : string
    {
        return 'String';
    }
}
