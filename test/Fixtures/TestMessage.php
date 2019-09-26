<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test\Fixtures;

class TestMessage
{
    public $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }
}
