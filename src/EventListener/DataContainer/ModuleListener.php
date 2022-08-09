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


namespace numero2\AvalexBundle\EventListener\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Message;
use Contao\ModuleModel;
use Exception;
use numero2\AvalexBundle\AvalexAPI;


class ModuleListener {


    /**
     * Checks if the entered API key is valid
     *
     * @param mixed $value
     * @param Contao\DataContainer $dc
     *
     * @return string
     *
     * @Callback(table="tl_module", target="fields.avalex_apikey.load")
     */
    public function checkAPIKey( $value, DataContainer $dc ) {

        if( $value && $dc->activeRecord->avalex_domain ) {

            $oModule = NULL;
            $oModule = ModuleModel::findById($dc->activeRecord->id);

            $oAPI = NULL;
            $oAPI = new AvalexAPI($value, $dc->activeRecord->avalex_domain);

            try {

                $oAPI->getContent($oModule, true);
                Message::addNew($GLOBALS['TL_LANG']['avalex']['msg']['key_valid']);

            } catch( Exception $e ) {

                Message::addError($e->getMessage());
            }
        }

        return $value;
    }
}
