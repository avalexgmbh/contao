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


class ModuleAvalexCancellationPolicy extends AvalexModule {


    /**
     * Module type
     * @var string
     */
    protected $moduleType = 'avalex_cancellation_policy';

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_avalex_cancellation_policy';
}
