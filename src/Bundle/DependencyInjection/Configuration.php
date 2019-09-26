<?php declare(strict_types=1);
/**
 * This file is part of pmg/central-backend.
 *
 * Copyright (c) PMG <https://www.pmg.com>. All rights reserved.
 */

namespace PMG\SqsTransport\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder() : TreeBuilder
    {
        $tree = new TreeBuilder('pmg_sqs_transport');
        $root = $tree->getRootNode();

        $root
            ->children()
            ->scalarNode('sqs_client_service')
                ->cannotBeEmpty()
                ->defaultValue('aws.sqs')
                ->info('The service ID for the AWS SqsClient')
            ->end()
        ;

        return $tree;
    }
}
