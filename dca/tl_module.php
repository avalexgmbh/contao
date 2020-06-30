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


/**
 * Add palettes to tl_module
 */
foreach( array_keys($GLOBALS['FE_MOD']['avalex']) as $type ) {
    $GLOBALS['TL_DCA']['tl_module']['palettes'][$type] = '{title_legend},name,headline,type;{config_legend},avalex_domain,avalex_apikey;{template_legend:hide},customTpl;{expert_legend:hide},guests,cssID,space';
}


/**
 * Add fields to tl_module
 */
$GLOBALS['TL_DCA']['tl_module']['fields']['avalex_domain'] = [
    'label'         => &$GLOBALS['TL_LANG']['tl_module']['avalex_domain']
,   'inputType'     => 'text'
,   'eval'          => ['mandatory'=>true, 'placeholder'=>'example.com', 'tl_class'=>'w50']
,   'sql'           => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['avalex_apikey'] = [
    'label'         => &$GLOBALS['TL_LANG']['tl_module']['avalex_apikey']
,   'inputType'     => 'text'
,   'eval'          => ['mandatory'=>true, 'tl_class'=>'w50']
,   'load_callback' => [ ['tl_module_avalex', 'checkAPIKey'] ]
,   'sql'           => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['avalex_cache'] = [
    'sql'           => "mediumblob NULL"
];


class tl_module_avalex extends \Backend {


    /**
     * Checks if the entered API key is valid
     *
     * @param mixed $value
     * @param Contao\DataContainer $dc
     *
     * @return string
     */
    public function checkAPIKey( $value, DataContainer $dc ) {

        if( $value && $dc->activeRecord->avalex_domain ) {

            $oModule = NULL;
            $oModule = \ModuleModel::findById($dc->activeRecord->id);

            $oAPI = NULL;
            $oAPI = new \numero2\avalex\AvalexAPI($value, $dc->activeRecord->avalex_domain);

            try {

                $oAPI->getContent($oModule, true);
                \Message::addNew($GLOBALS['TL_LANG']['avalex']['msg']['key_valid']);

            } catch( \Exception $e ) {

                \Message::addError($e->getMessage());
            }
        }

        return $value;
    }
}