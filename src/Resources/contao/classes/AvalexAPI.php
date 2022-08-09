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


namespace numero2\AvalexBundle;

use Contao\Config;
use Contao\Database;
use Contao\Date;
use Contao\Message;
use Contao\Module;
use Contao\ModuleModel;
use Contao\System;
use Exception;
use stdClass;
use Symfony\Component\HttpClient\HttpClient;


class AvalexAPI {


    /**
     * API Hosts
     * @var string
     */
    const API_HOST = 'https://avalex.de';
    const API_HOST_FALLBACK = 'https://proxy.avalex.de';


    /**
     * Module to Endpoint mapping
     * @var array
     */
    const ENDPOINTS_MAPPING = [
        'avalex_privacy_policy' => '/avx-datenschutzerklaerung'
    ,   'avalex_imprint' => '/avx-impressum'
    ,   'avalex_terms_conditions' => '/avx-bedingungen'
    ,   'avalex_cancellation_policy' => '/avx-widerruf'
    ,   'langs' => '/avx-get-domain-langs'
    ];


    /**
     * API Key
     * @var string
     */
    private $apiKey = null;


    /**
     * Domain
     * @var string
     */
    private $domain = null;


    /**
     * Cached list of available languages
     * @var string
     */
    private $langs = null;


    /**
     * Constructor
     *
     * @param string $apiKey
     * @param string $strDomain
     */
    public function __construct( $apiKey=null, $strDomain=null ) {

        if( !empty($apiKey) ) {
            $this->apiKey = $apiKey;
        } else {
            throw new Exception("No avalex API key given");
        }

        if( !empty($strDomain) ) {
            $this->domain = $strDomain;
        } else {
            throw new Exception("No avalex domain given");
        }
    }


    /**
     * Returns the content for the given module
     *
     * @param Contao\Module|Contao\ModuleModel $oModule
     * @param boolean $validationMode
     *
     * @return string
     */
    public function getContent( $oModule, $validationMode=false ) {

        $oCache = null;
        $oCache = json_decode($oModule->avalex_cache,1);

        // if empty or older than 6 hours force update of cache
        $updateCache = (empty($oCache) || empty($oCache['content']) || (time() - $oCache['date']) > (3600*6)) ? true : false;

        if( $updateCache || $validationMode ) {

            $langCacheKey = $this->apiKey.$this->domain;

            // get a list of available languages
            if( empty($this->langs) || empty($this->langs[$langCacheKey]) ) {

                $endpoint = self::ENDPOINTS_MAPPING['langs'];
                $response = $this->send($endpoint);

                if( !$response instanceof stdClass && $response !== false ) {

                    $this->langs[$langCacheKey] = json_decode($response,1);

                } else {

                    $msg = sprintf(
                        'Error while retrieving data from avalex (%s %s)'
                    ,   $endpoint
                    ,   $response->code . ' ' . $response->data
                    );

                    if( System::getContainer()->has('monolog.logger.contao.error') ) {
                        System::getContainer()->get('monolog.logger.contao.error')->error($msg);
                    } else {
                        System::log($msg, __METHOD__, TL_ERROR);
                    }

                    // insufficient license / wrong domain
                    if( $response->code == 400 ) {

                        $message = sprintf(
                            $GLOBALS['TL_LANG']['avalex']['msg']['update_failed'][$oModule->type]
                        ,   Date::parse(Config::get('datimFormat'), time())
                        ,   '('.$GLOBALS['TL_LANG']['avalex']['msg']['insufficient_license'].')'
                        );

                    // wrong api key
                    } else if( $response->code == 401 ) {

                        $message = $GLOBALS['TL_LANG']['avalex']['msg']['key_invalid'];

                    // unknown problem
                    } else {

                        $message = sprintf(
                            $GLOBALS['TL_LANG']['avalex']['msg']['update_failed'][$oModule->type]
                        ,   Date::parse(Config::get('datimFormat'), time())
                        ,   ''
                        );
                    }

                    if( $validationMode ) {
                        throw new Exception($message);
                    }

                    Message::addError($message,'BE');
                }
            }

            // update content for each available language
            if( !empty($this->langs[$langCacheKey]) ) {

                $endpoint = self::ENDPOINTS_MAPPING[$oModule->type];
                $moduleName = substr($endpoint, 5);

                $oCache = [];
                $oCache['date'] = time();
                $oCache['content'] = [];

                foreach( $this->langs[$langCacheKey] as $lang => $modules ) {

                    if( in_array($moduleName, array_keys($modules)) ) {

                        $response = $this->send($endpoint, false, $lang);

                        if( !$response instanceof stdClass && $response !== false ) {

                            $oCache['content'][$lang] = $response;

                        } else {

                            $msg = sprintf(
                                'Error while retrieving data from avalex (%s %s)'
                            ,   $endpoint
                            ,   $response->code . ' ' . $response->data
                            );

                            if( System::getContainer()->has('monolog.logger.contao.error') ) {
                                System::getContainer()->get('monolog.logger.contao.error')->error($msg);
                            } else {
                                System::log($msg, __METHOD__, TL_ERROR);
                            }

                            // insufficient license / wrong domain
                            if( $response->code == 400 ) {

                                $message = sprintf(
                                    $GLOBALS['TL_LANG']['avalex']['msg']['update_failed'][$oModule->type]
                                ,   Date::parse(Config::get('datimFormat'), time())
                                ,   '('.$GLOBALS['TL_LANG']['avalex']['msg']['insufficient_license'].')'
                                );

                            // wrong api key
                            } else if( $response->code == 401 ) {

                                $message = $GLOBALS['TL_LANG']['avalex']['msg']['key_invalid'];

                            // unknown problem
                            } else {

                                $message = sprintf(
                                    $GLOBALS['TL_LANG']['avalex']['msg']['update_failed'][$oModule->type]
                                ,   Date::parse(Config::get('datimFormat'), time())
                                ,   ''
                                );
                            }

                            if( $validationMode ) {
                                throw new Exception($message);
                            }

                            Message::addError($message,'BE');
                        }
                    }
                }

                if( !empty($oCache['content']) ) {

                    $oModel = null;
                    $oModel = ($oModule instanceof Module) ? $oModule->getModel() : $oModule;

                    // not using the models save method bc this does not work in 3.1
                    Database::getInstance()->prepare("UPDATE ".ModuleModel::getTable()." SET avalex_cache = ? WHERE id = ?")->execute( json_encode($oCache), $oModel->id );
                }
            }
        }

        if( !empty($oCache['content']) ) {
            return $oCache['content'];
        }

        return false;
    }


    /**
     * Send request to the API
     *
     * @param string $uri
     * @param boolean $useFallback
     * @param string $lang
     *
     * @return string
     */
    private function send( $uri=null, $useFallback=false, $lang='de' ) {

        $url = ($useFallback ? self::API_HOST_FALLBACK : self::API_HOST) . $uri . '?' . http_build_query([
            'apikey' => $this->apiKey
        ,   'domain' => $this->domain
        ,   'version' => '3.0.1'
        ,   'lang' => $lang
        ]);

        try {

            $client = null;
            $client = HttpClient::create([
                'timeout' => 5
            ,   'max_duration' => 5
            ,   'verify_peer' => false
            ,   'verify_host' => false
            ]);

            try {

                $response = null;
                $response = $client->request('GET', $url);

            } catch( Exception $e ) {

                // use fallback domain if the main one does not work
                if( !$useFallback && self::API_HOST_FALLBACK ) {
                    return $this->send($uri, true);
                }

                throw $e;
            }

            if( $response->getStatusCode() != 200 ) {

                $message = $response->getContent(false);

                $return = new stdClass;
                $return->code = $response->getStatusCode();
                $return->data = $message ?? '';

                return $return;

            } else {
                return $response->getContent(false);
            }

        } catch( Exception $e ) {

            $msg = 'Exception while retrieving data from avalex (' . $e->getMessage() . ')';
            if( System::getContainer()->has('monolog.logger.contao.error') ) {
                System::getContainer()->get('monolog.logger.contao.error')->error($msg);
            } else {
                System::log($msg, __METHOD__, TL_ERROR);
            }

            return false;
        }
    }
}
