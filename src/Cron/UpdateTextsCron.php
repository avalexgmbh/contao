<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use numero2\AvalexBundle\Util\AvalexUtil;


#[AsCronJob('hourly')]
class UpdateTextsCron {


    private AvalexUtil $avalexUtil;


    public function __construct( AvalexUtil $avalexUtil ) {

        $this->avalexUtil = $avalexUtil;
    }


    /**
     * Updates all texts older than AvalexUtil::MAX_AGE
     */
    public function __invoke(): void {

        $this->avalexUtil->updateAll();
    }
}
