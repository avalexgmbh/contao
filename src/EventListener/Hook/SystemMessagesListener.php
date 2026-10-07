<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\EventListener\Hook;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use numero2\AvalexBundle\Util\AvalexUtil;
use numero2\AvalexBundle\Util\MessageUtil;


#[AsHook('getSystemMessages')]
class SystemMessagesListener {


    /**
     * Texts not updated for this long (in seconds) will be reported as outdated
     */
    private const OUTDATED_AFTER = 24 * 3600;


    private AvalexUtil $avalexUtil;

    private MessageUtil $messageUtil;


    public function __construct( AvalexUtil $avalexUtil, MessageUtil $messageUtil ) {

        $this->avalexUtil = $avalexUtil;
        $this->messageUtil = $messageUtil;
    }


    /**
     * Shows the date of the last update of each module / content element and warns about failed updates and outdated texts
     *
     * @return string
     */
    public function __invoke(): string {

        $messages = [];

        foreach( AvalexUtil::TABLES as $table ) {

            foreach( $this->avalexUtil->findAll($table) as $record ) {

                if( !AvalexUtil::isConfigured($record) ) {
                    continue;
                }

                $lastUpdate = $this->avalexUtil->getLastUpdate($record);

                if( $lastUpdate === null || (time() - $lastUpdate) > self::OUTDATED_AFTER ) {
                    $messages[] = '<p class="tl_error">'.$this->messageUtil->getOutdatedMessage($table, $record).'</p>';
                } elseif( ($failed = $this->messageUtil->getFailedMessage($table, $record)) !== null ) {
                    $messages[] = '<p class="tl_error">'.$failed.'</p>';
                } else {
                    $messages[] = '<p class="tl_info">'.$this->messageUtil->getLastUpdateMessage($table, $record).'</p>';
                }
            }
        }

        return implode('', $messages);
    }
}
