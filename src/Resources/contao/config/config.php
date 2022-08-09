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


use numero2\AvalexBundle\ModuleAvalexCancellationPolicy;
use numero2\AvalexBundle\ModuleAvalexImprint;
use numero2\AvalexBundle\ModuleAvalexPrivacyPolicy;
use numero2\AvalexBundle\ModuleAvalexTermsConditions;


/**
 * FRONT END MODULES
 */
$GLOBALS['FE_MOD']['avalex'] = [
    'avalex_privacy_policy'      => ModuleAvalexPrivacyPolicy::class
,   'avalex_imprint'             => ModuleAvalexImprint::class
,   'avalex_terms_conditions'    => ModuleAvalexTermsConditions::class
,   'avalex_cancellation_policy' => ModuleAvalexCancellationPolicy::class
];