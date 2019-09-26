<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Exception\RuntimeException;

abstract class SqsAttributeStamp implements StampInterface
{
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function fromAttributeArray(string $name, array $attribute) : self
    {
        switch ($attribute['DataType'] ?? 'String') {
            case 'Number':
                return new SqsNumberAttributeStamp($name, $attribute['StringValue']);
            case 'String':
                return new SqsStringAttributeStamp($name, $attribute['StringValue']);
            default:
                throw new RuntimeException(sprintf(
                    'Unsupported message attribute data type: "%s"',
                    $attribute['DataType']
                ));
        }
    }

    public function getAttributeName() : string
    {
        return $this->name;
    }

    abstract public function getAttributeValue();

    abstract public function getAttributeDataType() : string;

    public function getAttributeValueKey() : string
    {
        return 'StringValue';
    }
}
