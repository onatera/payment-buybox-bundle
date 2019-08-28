<?php

namespace Onatera\Payment\BuyboxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class OnateraPaymentBuyboxExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->process($configuration->getConfigTree(), $configs);

        $xmlLoader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $xmlLoader->load('services.yml');

        $container->setParameter('payment.buybox.username', $config['username']);
        $container->setParameter('payment.buybox.password', $config['password']);
        $container->setParameter('payment.buybox.signature', $config['signature']);
        $container->setParameter('payment.buybox.express_checkout.return_url', $config['return_url']);
        $container->setParameter('payment.buybox.express_checkout.cancel_url', $config['cancel_url']);
        $container->setParameter('payment.buybox.express_checkout.notify_url', $config['notify_url']);
        $container->setParameter('payment.buybox.express_checkout.useraction', $config['useraction']);
        $container->setParameter('payment.buybox.debug', $config['debug']);
    }
}
