<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2022 Leo Feyer
 *
 * @package   avalex
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL
 * @copyright 2022 numero2 - Agentur für digitales Marketing GbR
 * @copyright 2022 avalex GmbH
 */


namespace numero2\AvalexBundle\EventListener\Hooks;

use Contao\Config;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Date;
use Contao\ModuleModel;


class HooksListener {


    /**
     * Checks if the current api key is valid / configure
     *
     * @return boolean
     *
     * @Hook("getSystemMessages")
     */
    public function getSystemMessages() {

        $aMessages = [];

        // find frontend module
        $oModules = null;
        $oModules = ModuleModel::findBy([ModuleModel::getTable().".type IN ('".implode("','", array_keys($GLOBALS['FE_MOD']['avalex']))."')"],[]);

        if( $oModules ) {

            while( $oModules->next() ) {

                $oCache = null;
                $oCache = json_decode($oModules->avalex_cache);

                if( $oCache && $oCache->date ) {

                    $msg = sprintf(
                        $GLOBALS['TL_LANG']['avalex']['msg']['last_update'][$oModules->type]
                        ,   Date::parse(Config::get('datimFormat'), $oCache->date)
                    );

                    $aMessages[] = '<p class="tl_info">'.$msg.'</p>';
                }
            }
        }

        return implode('',$aMessages);
    }


    /**
     * Updates the modules if necessary
     *
     * @return boolean
     *
     * @CronJob("hourly")
     */
    public function updateModuleContents() {

        $oModules = null;
        $oModules = ModuleModel::findBy([ModuleModel::getTable().".type IN ('".implode("','", array_keys($GLOBALS['FE_MOD']['avalex']))."')"],[]);

        if( $oModules ) {

            while( $oModules->next() ) {

                $oAPI = null;
                $oAPI = new AvalexAPI( $oModules->avalex_apikey,  $oModules->avalex_domain );

                $oAPI->getContent( $oModules->current() );
            }
        }
    }
}
