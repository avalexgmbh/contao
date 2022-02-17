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


namespace numero2\avalex;

use Contao\Config;
use Contao\Date;


class AvalexBackend {


    /**
     * Checks if the current api key is valid / configure
     *
     * @return boolean
     */
    public function getSystemMessages() {

        $aMessages = [];

        // check extension update
        $moduleUpdate = false;
        $moduleUpdate = $this->getMostRecentVersion();

        if( $moduleUpdate ) {

            $msg = sprintf(
                $GLOBALS['TL_LANG']['avalex']['msg']['module_update']
            ,   $moduleUpdate
            );

            $aMessages[] = '<p class="tl_error">'.$msg.'</p>';
        }

        // find frontend module
        $oModules = null;
        $oModules = \ModuleModel::findBy([\ModuleModel::getTable().".type IN ('".implode("','", array_keys($GLOBALS['FE_MOD']['avalex']))."')"],[]);

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
     */
    public function updateModuleContents() {

        $oModules = null;
        $oModules = \ModuleModel::findBy([\ModuleModel::getTable().".type IN ('".implode("','", array_keys($GLOBALS['FE_MOD']['avalex']))."')"],[]);

        if( $oModules ) {

            $oAPI = null;
            $oAPI = new AvalexAPI( $oModules->avalex_apikey,  $oModules->avalex_domain );

            while( $oModules->next() ) {

                $oAPI->getContent( $oModules->current() );
            }
        }
    }


    /**
     * Checks if there is a newer version of the avalex module available
     *
     * @return string
     */
    private function getMostRecentVersion() {

        $currentVersion = null;
        $currentVersion = file_get_contents( __DIR__.'/../version.txt' );

        $latestVersion = Config::get('avalexLatestVersion');

        if( $latestVersion && version_compare($currentVersion, $latestVersion, '<') ) {
            return $latestVersion;
        }

        return false;
    }


    /**
     * Gets the lates version number from GitHub
     * and stores it in config
     */
    public function updateLastExtensionVersion() {

        $versionURI = 'https://github.com/avalexgmbh/contao/raw/master/version.txt';
        $latestVersion = null;

        // Contao 4 and above
        if( class_exists('\GuzzleHttp\Client') ) {

            $request = new \GuzzleHttp\Client(
                [
                    \GuzzleHttp\RequestOptions::TIMEOUT         => 5
                ,   \GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => 5
                ,   \GuzzleHttp\RequestOptions::HTTP_ERRORS     => false
                ]
            );

            try {

                $response = $request->get($versionURI);

                if( $response->getStatusCode() == 200 ) {
                    $latestVersion = trim($response->getBody()->getContents());
                }

            } catch( \Exception $e ) {
            }


        // Contao 3
        } else {

            $oRequest = null;
            $oRequest = new \Request();

            try {

                $oRequest->redirect = true;

            } catch( \Exception $e ) {

                // older version, maybe 3.1 cannot handle redirects automatically
                $oRequest->send($versionURI);

                if( $oRequest->code == 302 ) {

                    if( !empty($oRequest->headers['Location']) ) {
                        $versionURI = $oRequest->headers['Location'];
                    }
                }
            }

            $oRequest->send($versionURI);

            if( $oRequest->code == 200 ) {

                $latestVersion = trim($oRequest->response);
            }
        }

        if( $latestVersion ) {

            $oConfig = null;
            $oConfig = Config::getInstance();

            if( method_exists($oConfig, 'persist') ) {

                Config::persist('avalexLatestVersion', $latestVersion);

            } else {

                $strKey = "\$GLOBALS['TL_CONFIG']['avalexLatestVersion']";
                $oConfig->add($strKey, $latestVersion);
            }
        }
    }
}