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

use Contao\BackendTemplate;
use Contao\FrontendTemplate;


abstract class AvalexModule extends \Module {


    /**
     * Module type
     * @var string
     */
    protected $moduleType;

    /**
     * API instance
     * @var string
     */
    protected $oAPI;


    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate() {

        if( TL_MODE == 'BE' ) {

            $objTemplate = new BackendTemplate('be_wildcard');

            if( empty($this->FMD_TYPE) ) {
                $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD'][$this->moduleType][0].' ###';
            } else {
                $objTemplate->wildcard = '### '.utf8_strtoupper($GLOBALS['TL_LANG']['FMD'][$this->FMD_TYPE][0]).' ###';
            }

            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }


    /**
     * Generate module
     */
    protected function compile() {

        global $objPage;

        $this->Template = new FrontendTemplate(empty($this->customTpl)?$this->strTemplate:$this->customTpl);

        if( !empty($this->avalex_apikey) && !empty($this->avalex_domain) ) {

            $this->oAPI = null;
            $this->oAPI = new AvalexAPI($this->avalex_apikey, $this->avalex_domain);

            $content = null;
            $content = $this->oAPI->getContent( $this );

            $this->Template->content = "";

            if( $content ) {

                // single-language (old version < 2.0.0)
                if( !is_array($content) ) {

                    $this->Template->content = $content;

                // multi-language (new version)
                } else {

                    $lang = strtolower(substr($objPage->language, 0, 2));
                    $this->Template->content = !empty($content[$lang]) ? $content[$lang] : $content['de'];
                }
            }
        }
    }


    /**
     * Return the model
     *
     * @return Model
     */
    public function getModel() {
        return $this->objModel;
    }
}
