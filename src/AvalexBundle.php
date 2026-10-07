<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle;

use numero2\AvalexBundle\DependencyInjection\Compiler\CacheTagManagerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;


class AvalexBundle extends Bundle {


    /**
     * {@inheritdoc}
     */
    public function getPath(): string {

        return \dirname(__DIR__);
    }


    /**
     * {@inheritdoc}
     */
    public function build( ContainerBuilder $container ): void {

        parent::build($container);

        $container->addCompilerPass(new CacheTagManagerPass());
    }
}
