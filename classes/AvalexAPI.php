<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2020 Leo Feyer
 *
 * @package   avalex
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL
 * @copyright 2020 numero2 - Agentur für digitales Marketing GbR
 * @copyright 2020 avalex GmbH
 */


namespace numero2\avalex;

use Contao\Config;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\Message;
use Contao\Module;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;


class AvalexAPI {


    /**
     * API Hosts
     * @var string
     */
    const API_HOST = 'https://avalex.de';
    const API_HOST_FALLBACK = 'https://proxy.avalex.de';


    /**
     * Module to Endpoint mpaaing
     * @var array
     */
    const ENDPOINTS_MAPPING = [
        'avalex_privacy_policy' => '/avx-datenschutzerklaerung'
    ,   'avalex_imprint' => '/avx-impressum'
    ,   'avalex_terms_conditions' => '/avx-bedingungen'
    ,   'avalex_cancellation_policy' => '/avx-widerruf'
    ];


    /**
     * API Key
     * @var string
     */
    private $apiKey = NULL;


    /**
     * Domain
     * @var string
     */
    private $domain = NULL;


    /**
     * Constructor
     *
     * @param $apiKey
     *
     * @return numero2/AvalexAPI
     */
    public function __construct( $apiKey=NULL, $strDomain=NULL ) {

        if( !empty($apiKey) ) {
            $this->apiKey = $apiKey;
        } else {
            throw new \Exception("No avalex API key given");
        }

        if( !empty($strDomain) ) {
            $this->domain = $strDomain;
        } else {
            throw new \Exception("No avalex domain given");
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

        $oCache = NULL;
        $oCache = json_decode($oModule->avalex_cache);

        // if empty or older than 6 hours force update of cache
        $updateCache = (empty($oCache) || empty($oCache->content) || (time() - $oCache->date) > (3600*6)) ? true : false;

        if( $updateCache || $validationMode ) {

            $endpoint = self::ENDPOINTS_MAPPING[$oModule->type];
            $response = $this->send($endpoint);

            if( !$response instanceof \stdClass && $response !== false ) {

                if( !$oCache instanceof \stdClass ) {
                    $oCache = new \stdClass;
                }

                $oCache->date = time();
                $oCache->content = $response;

                $oModel = NULL;
                $oModel = ($oModule instanceof Module) ? $oModule->getModel() : $oModule;

                // not using the models save method bc this does not work in 3.1
                Database::getInstance()->prepare("UPDATE ".ModuleModel::getTable()." SET avalex_cache = ? WHERE id = ? ")->execute( json_encode($oCache), $oModel->id );

            } else {

                System::log(
                    sprintf(
                        'Error while retrieving data from avalex (%s %s)'
                    ,   $endpoint
                    ,   $response->code . ' ' . $response->data
                    )
                ,   __METHOD__
                ,   TL_ERROR
                );

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
                    throw new \Exception($message);
                }

                Message::addError($message,'BE');
            }
        }

        if( $oCache->content ) {
            return $oCache->content;
        }

        return false;
    }


    /**
     * Send request to the API
     *
     * @param String $uri
     *
     * @return String
     */
    private function send( $uri=NULL, $useFallback=false ) {

        $url  = ($useFallback ? self::API_HOST_FALLBACK : self::API_HOST) . $uri . '?apikey=' . $this->apiKey . '&domain=' . $this->domain;

        try {

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

                    $response = $request->get($url);

                } catch( \Exception $e ) {

                    // use fallback domain if the main one does not work
                    if( !$useFallback && self::API_HOST_FALLBACK ) {
                        return $this->send($uri, true);
                    }

                    throw $e;
                }

                if( $response->getStatusCode() != 200 ) {

                    $message = json_decode( $response->getBody()->getContents() );

                    $return = new \stdClass;
                    $return->code = $response->getStatusCode();
                    $return->data = $message->message ? $message->message : $response->getReasonPhrase();

                    return $return;

                } else {
                    return $response->getBody()->getContents();
                }

            // Contao 3
            } else {

                $oRequest = NULL;
                $oRequest = new \Request();

                $oRequest->send($url);

                if( $oRequest->error && strpos($oRequest->error, '110') !== FALSE ) {

                    // use fallback domain if the main one does not work
                    if( !$useFallback ) {
                        return $this->send($uri, true);
                    }
                }

                if( $oRequest->code != 200 ) {

                    $message = json_decode( $oRequest->response );

                    $return = new \stdClass;
                    $return->code =$oRequest->code;
                    $return->data = $message->message ? $message->message : $oRequest->error;

                    return $return;

                } else {

                    return $oRequest->response;
                }
            }

        } catch( \Exception $e ) {

            System::log('Exception while retrieving data from avalex (' . $e->getMessage() . ')', __METHOD__, TL_ERROR);
            return false;
        }
    }
}