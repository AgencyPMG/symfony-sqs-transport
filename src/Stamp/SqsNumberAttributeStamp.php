<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Stamp;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

final class SqsNumberAttributeStamp extends SqsAttributeStamp
{
    /**
     * @var int|float
     */
    private $value;

    public function __construct(string $name, $value)
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects $value to be numeric',
                self::class
            ));
        }

        parent::__construct($name);
        $this->value = $value;
    }

    public function getAttributeValue() : string
    {
        return (string) $this->value;
    }

    public function getAttributeDataType() : string
    {
        return 'Number';
    }
}
