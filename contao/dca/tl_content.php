<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


use numero2\AvalexBundle\Util\AvalexUtil;


// the element title is not available in Contao 5.3
$title = isset($GLOBALS['TL_DCA']['tl_content']['fields']['title']) ? ',title' : '';

foreach( array_keys(AvalexUtil::TYPES) as $type ) {
    $GLOBALS['TL_DCA']['tl_content']['palettes'][$type] = '{type_legend},type,headline'.$title.';{avalex_legend},avalex_domain,avalex_apikey;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';
}


foreach( AvalexUtil::DCA_FIELDS as $name => $field ) {
    $GLOBALS['TL_DCA']['tl_content']['fields'][$name] = $field;
}
