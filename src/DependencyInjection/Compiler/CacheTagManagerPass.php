<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;


/**
 * Injects the service used for invalidating HTTP cache tags, which got renamed
 * from "contao.cache.entity_tags" (Contao 5.3) to "contao.cache.tag_manager" (Contao 5.5+)
 */
class CacheTagManagerPass implements CompilerPassInterface {


    private const TARGET_SERVICE = 'numero2_avalex.util.avalex';


    /**
     * {@inheritdoc}
     */
    public function process( ContainerBuilder $container ): void {

        if( !$container->hasDefinition(self::TARGET_SERVICE) ) {
            return;
        }

        $cacheTagManager = null;

        foreach( ['contao.cache.tag_manager', 'contao.cache.entity_tags'] as $id ) {

            if( $container->has($id) ) {
                $cacheTagManager = new Reference($id);
                break;
            }
        }

        $container->getDefinition(self::TARGET_SERVICE)->setArgument('$cacheTagManager', $cacheTagManager);
    }
}
