<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\Util;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\StringUtil;
use numero2\AvalexBundle\Exception\AvalexApiException;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * Generates the back end messages for avalex modules and content elements
 */
class MessageUtil {


    private ContaoFramework $framework;

    private TranslatorInterface $translator;

    private AvalexUtil $avalexUtil;


    public function __construct( ContaoFramework $framework, TranslatorInterface $translator, AvalexUtil $avalexUtil ) {

        $this->framework = $framework;
        $this->translator = $translator;
        $this->avalexUtil = $avalexUtil;
    }


    /**
     * Returns the message containing the date of the last update or null if there was none yet
     *
     * @param string $table
     * @param array $record
     *
     * @return string|null
     */
    public function getLastUpdateMessage( string $table, array $record ): ?string {

        $lastUpdate = $this->avalexUtil->getLastUpdate($record);

        if( $lastUpdate === null ) {
            return null;
        }

        return $this->trans('avalex.msg.last_update.'.$record['type'], [$this->getLabel($table, $record), $this->formatDate($lastUpdate)]);
    }


    /**
     * Returns the message for texts that are outdated or have never been updated
     *
     * @param string $table
     * @param array $record
     *
     * @return string
     */
    public function getOutdatedMessage( string $table, array $record ): string {

        $lastUpdate = $this->avalexUtil->getLastUpdate($record);

        if( $lastUpdate === null ) {
            return $this->trans('avalex.msg.never_updated', [$this->getLabel($table, $record)]);
        }

        return $this->trans('avalex.msg.outdated', [$this->getLabel($table, $record), $this->formatDate($lastUpdate)]);
    }


    /**
     * Returns the message for texts whose last update attempt failed or null if it did not
     *
     * @param string $table
     * @param array $record
     *
     * @return string|null
     */
    public function getFailedMessage( string $table, array $record ): ?string {

        $lastFailure = $this->avalexUtil->getLastFailure($record);

        if( $lastFailure === null ) {
            return null;
        }

        return $this->trans('avalex.msg.failed', [$this->getLabel($table, $record), $this->formatDate($lastFailure)]);
    }


    /**
     * Returns the message for a successful update
     *
     * @return string
     */
    public function getSuccessMessage(): string {

        return $this->trans('avalex.msg.key_valid');
    }


    /**
     * Returns a human readable error message for the given exception
     *
     * @param \numero2\AvalexBundle\Exception\AvalexApiException $e
     * @param string $type
     *
     * @return string
     */
    public function getErrorMessage( AvalexApiException $e, string $type ): string {

        if( $e->isInvalidKey() ) {
            return $this->trans('avalex.msg.key_invalid');
        }

        $message = $this->trans('avalex.msg.update_failed.'.$type, [$this->formatDate(time())]);

        if( $e->isInsufficientLicense() ) {
            $message .= ' ('.$this->trans('avalex.msg.insufficient_license').')';
        }

        return $message;
    }


    /**
     * Returns a label identifying the given record, e.g. 'module "Imprint"'
     *
     * @param string $table
     * @param array $record
     *
     * @return string
     */
    public function getLabel( string $table, array $record ): string {

        if( $table === 'tl_module' ) {
            return $this->trans('avalex.label.module', [StringUtil::specialchars($record['name'] ?? '')]);
        }

        // content elements have a title in newer Contao versions
        if( !empty($record['title']) ) {
            return $this->trans('avalex.label.content_title', [StringUtil::specialchars($record['title'])]);
        }

        return $this->trans('avalex.label.content', [$record['id']]);
    }


    /**
     * Formats the given timestamp using the configured date and time format
     *
     * @param int $timestamp
     *
     * @return string
     */
    public function formatDate( int $timestamp ): string {

        $config = $this->framework->getAdapter(Config::class);

        return $this->framework->getAdapter(Date::class)->parse($config->get('datimFormat'), $timestamp);
    }


    private function trans( string $id, array $parameters=[] ): string {

        return $this->translator->trans($id, $parameters, 'contao_default');
    }
}
