<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test\Bundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use PMG\SqsTransport\Test\TestCase;
use PMG\SqsTransport\Test\ValidDsnProvider;
use PMG\SqsTransport\SqsTransport;

class PmgSqsTransportBundleTest extends TestCase
{
    use ValidDsnProvider;

    private $kernel;

    /**
     * @dataProvider validDsn
     */
    public function testSqsUrlsRegisterExpectedTransport(string $dsn, string $queueUrl)
    {
        $container = $this->bootKernel(md5(__FUNCTION__.$dsn), [
            'test' => $dsn,
        ]);

        $transport = $container->get('messenger.transport.test');
        $this->assertInstanceOf(SqsTransport::class, $transport);
        $this->assertSame($queueUrl, $transport->getConfig()->getQueueUrl());
    }

    /**
     * @dataProvider validDsn
     */
    public function testOptionsCanBeSpecifiedInQueryString(string $dsn)
    {
        $container = $this->bootKernel(md5(__FUNCTION__.$dsn), [
            'test' => $dsn.'?receive_count=5&receive_wait=10',
        ]);

        $transport = $container->get('messenger.transport.test');
        $this->assertInstanceOf(SqsTransport::class, $transport);
        $this->assertSame(5, $transport->getConfig()->getReceiveCount());
        $this->assertSame(10, $transport->getConfig()->getReceiveWait());
    }

    /**
     * @dataProvider validDsn
     */
    public function testOptionsCanBeSpecifiedInOptions(string $dsn)
    {
        $container = $this->bootKernel(md5(__FUNCTION__.$dsn), [
            'test' => [
                'dsn' => $dsn,
                'options' => [
                    'receive_wait' => 10,
                    'receive_count' => 5,
                ],
            ],
        ]);

        $transport = $container->get('messenger.transport.test');
        $this->assertInstanceOf(SqsTransport::class, $transport);
        $this->assertSame(5, $transport->getConfig()->getReceiveCount());
        $this->assertSame(10, $transport->getConfig()->getReceiveWait());
    }

    protected function tearDown() : void
    {
        if (!$this->kernel) {
            $this->kernel->shutdown();
        }
    }

    private function bootKernel(string $env, array $transportConfig) : ContainerInterface
    {
        $k = $this->kernel = new TestKernel($env, $transportConfig);
        $k->boot();

        $container = $k->getContainer();

        return $container->has('test.service_container') ? $container->get('test.service_container') : $container;
    }
}
