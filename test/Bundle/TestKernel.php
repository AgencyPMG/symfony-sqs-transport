<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Test\Bundle;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use PMG\SqsTransport\Bundle\PmgSqsTransportBundle;
use PMG\SqsTransport\Test\TestCase;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private $transportConfig;

    public function __construct(string $env, array $transportConfig)
    {
        parent::__construct($env, true);
        $this->transportConfig = $transportConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function registerBundles() : iterable
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Aws\Symfony\AwsBundle(),
            new PmgSqsTransportBundle(),
        ];
    }

    public function getProjectDir() : string
    {
        return __DIR__;
    }

    public function getCacheDir() : string
    {
        return __DIR__.'/var/cache/'.$this->getEnvironment();
    }

    public function getLogDir() : string
    {
        return __DIR__.'/var/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader) : void
    {
        $container->loadFromExtension('aws', [
            'version' => 'latest',
            'region' => 'us-east-1',
            'Sqs' => [
                'endpoint' => getenv('SQS_ENDPOINT') ?: TestCase::DEFAULT_ENDPOINT,
            ],
        ]);

        $container->loadFromExtension('framework', [
            'secret' => 'shhhh',
            'router' => [
                'utf8' => true,
            ],
            'test' => true,
            'messenger' => [
                'transports' => $this->transportConfig,
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureRoutes(RouteCollectionBuilder $routes) : void
    {
        // noop
    }
}
