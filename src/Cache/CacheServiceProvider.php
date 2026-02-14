<?php

declare(strict_types=1);

namespace CFXP\Core\Cache;

use CFXP\Core\Container\ContainerInterface;
use CFXP\Core\ServiceProviderInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class CacheServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(CacheInterface::class, function (ContainerInterface $container) {
            $config = $container->get('config');
            
            // Default to storage/cache directory
            $cacheDirectory = $config->get('cache.path', dirname(__DIR__, 2) . '/storage/cache');
            
            return new FileCache($cacheDirectory);
        });

        $container->alias('cache', CacheInterface::class);
    }

    public function boot(ContainerInterface $container, ?EventDispatcherInterface $dispatcher = null): void
    {
    }
}
