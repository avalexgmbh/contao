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
 * FRONT END MODULES
 */
$GLOBALS['FE_MOD']['avalex'] = [
    'avalex_privacy_policy'      => '\numero2\avalex\ModuleAvalexPrivacyPolicy'
,   'avalex_imprint'             => '\numero2\avalex\ModuleAvalexImprint'
,   'avalex_terms_conditions'    => '\numero2\avalex\ModuleAvalexTermsConditions'
,   'avalex_cancellation_policy' => '\numero2\avalex\ModuleAvalexCancellationPolicy'
];


/**
 * HOOKS
 */
$GLOBALS['TL_HOOKS']['getSystemMessages'][] = ['\numero2\avalex\AvalexBackend', 'getSystemMessages'];


/**
 * CRONJOBS
 */
$GLOBALS['TL_CRON']['daily'][] = ['\numero2\avalex\AvalexBackend', 'updateLastExtensionVersion'];
$GLOBALS['TL_CRON']['hourly'][] = ['\numero2\avalex\AvalexBackend', 'updateModuleContents'];