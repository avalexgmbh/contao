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
 * Register the namespaces
 */
ClassLoader::addNamespaces([
    'numero2\avalex',
]);


/**
 * Register the classes
 */
ClassLoader::addClasses([

    // Classes
    'numero2\avalex\AvalexAPI'     => 'system/modules/avalex/classes/AvalexAPI.php',
    'numero2\avalex\AvalexBackend' => 'system/modules/avalex/classes/AvalexBackend.php',
    'numero2\avalex\AvalexModule'  => 'system/modules/avalex/classes/AvalexModule.php',

    // Modules
    'numero2\avalex\ModuleAvalexPrivacyPolicy'      => 'system/modules/avalex/modules/ModuleAvalexPrivacyPolicy.php',
    'numero2\avalex\ModuleAvalexImprint'            => 'system/modules/avalex/modules/ModuleAvalexImprint.php',
    'numero2\avalex\ModuleAvalexTermsConditions'    => 'system/modules/avalex/modules/ModuleAvalexTermsConditions.php',
    'numero2\avalex\ModuleAvalexCancellationPolicy' => 'system/modules/avalex/modules/ModuleAvalexCancellationPolicy.php',
]);


/**
 * Register the templates
 */
TemplateLoader::addFiles([
    'mod_avalex_privacy_policy'      => 'system/modules/avalex/templates/modules',
    'mod_avalex_imprint'             => 'system/modules/avalex/templates/modules',
    'mod_avalex_terms_conditions'    => 'system/modules/avalex/templates/modules',
    'mod_avalex_cancellation_policy' => 'system/modules/avalex/templates/modules',
]);
