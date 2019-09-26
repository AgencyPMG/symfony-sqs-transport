<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Bundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use PMG\SqsTransport\SqsTransportFactory;

class PmgSqsTransportExtension extends ConfigurableExtension
{
    public function loadInternal(array $config, ContainerBuilder $container) : void
    {
        $container->register('pmg_sqs_transport.factory', SqsTransportFactory::class)
            ->addArgument(new Reference($config['sqs_client_service']))
            ->addTag('messenger.transport_factory')
            ;
    }

    public function getNamespace() : string
    {
        return 'https://symfony-bundles.pmg.com/schema/dic/sqs-transport-bundle';
    }

    public function getXsdValidationBasePath()
    {
        return false;
    }

    public function getAlias() : string
    {
        return 'pmg_sqs_transport';
    }
}
