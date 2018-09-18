<?php

namespace Wikimedia\ToolforgeBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ToolforgeExtension extends Extension {

    public function load(array $configs, ContainerBuilder $container) {
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
        $loader->load('services.yml');
    }
}
