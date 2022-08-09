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
    'inputType'     => 'text'
,   'eval'          => ['mandatory'=>true, 'placeholder'=>'example.com', 'tl_class'=>'w50']
,   'sql'           => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['avalex_apikey'] = [
    'inputType'     => 'text'
,   'eval'          => ['mandatory'=>true, 'tl_class'=>'w50']
,   'sql'           => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['avalex_cache'] = [
    'sql'           => "mediumblob NULL"
];
