<?php

namespace AveSystems\ObjectResolverBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * The class to manage the configuration of the bundle.
 *
 * @author Artem Burykin <nisoartem@gmail.com>
 */
class ObjectResolverExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $annotation = $config['serialized_name_annotation'] ?? null;
        $container->setParameter('object_resolver.serialized_name_annotation', $annotation);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
