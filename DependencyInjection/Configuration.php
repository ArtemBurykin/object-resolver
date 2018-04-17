<?php

namespace AveSystems\ObjectResolverBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The configuration description.
 *
 * @author Artem Burykin <nisoartem@gmail.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('object_resolver');
        $rootNode->children()
            ->scalarNode('serialized_name_annotation')->defaultNull()->end()
        ->end();

        return $treeBuilder;
    }
}
