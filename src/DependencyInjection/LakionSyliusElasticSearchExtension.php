<?php

namespace Lakion\SyliusElasticSearchBundle\DependencyInjection;

use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class LakionSyliusElasticSearchExtension extends AbstractResourceExtension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('services.xml');
        $this->createFilterSetsParameter($config, $container);
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    private function createFilterSetsParameter(array $config, ContainerBuilder $container)
    {
        $container->setParameter('lakion_sylius_elastic_search.filter_sets', reset($config)['filter_sets']);
    }
}
