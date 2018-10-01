<?php

declare(strict_types=1);

namespace Wikimedia\ToolforgeBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ToolforgeExtension extends Extension implements PrependExtensionInterface
{

    /**
     * @param mixed[] $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Process config.
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter('toolforge.oauth.consumer_key', $config['oauth']['consumer_key']);
        $container->setParameter('toolforge.oauth.consumer_secret', $config['oauth']['consumer_secret']);
        $container->setParameter('toolforge.oauth.logged_in_user', $config['oauth']['logged_in_user']);
        $container->setParameter('toolforge.intuition.domain', $config['intuition']['domain']);

        // Load services.
        $configDir = dirname(__DIR__).'/Resources/config';
        $loader = new YamlFileLoader($container, new FileLocator($configDir));
        $loader->load('services.yaml');
    }

    /**
     * Allow an extension to prepend the extension configurations.
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Add the bundle's templates directory to Twig.
        $container->prependExtensionConfig('twig', [
            'paths' => [
                dirname(__DIR__).'/Resources/templates' => 'toolforge',
            ],
        ]);
    }
}
