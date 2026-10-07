<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Message;
use numero2\AvalexBundle\Exception\AvalexApiException;
use numero2\AvalexBundle\Util\AvalexUtil;
use numero2\AvalexBundle\Util\MessageUtil;
use Symfony\Component\HttpFoundation\RequestStack;


class AvalexListener {


    private ContaoFramework $framework;

    private RequestStack $requestStack;

    private AvalexUtil $avalexUtil;

    private MessageUtil $messageUtil;


    public function __construct( ContaoFramework $framework, RequestStack $requestStack, AvalexUtil $avalexUtil, MessageUtil $messageUtil ) {

        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->avalexUtil = $avalexUtil;
        $this->messageUtil = $messageUtil;
    }


    /**
     * Shows the date of the last update when editing a record
     *
     * @param \Contao\DataContainer|null $dc
     */
    #[AsCallback('tl_content', target: 'config.onload')]
    #[AsCallback('tl_module', target: 'config.onload')]
    public function showLastUpdate( ?DataContainer $dc=null ): void {

        $request = $this->requestStack->getCurrentRequest();

        if( !$dc?->id || !$request || !$request->isMethod('GET') || $request->query->get('act') !== 'edit' ) {
            return;
        }

        $record = $this->avalexUtil->find($dc->table, (int) $dc->id);

        if( !$record ) {
            return;
        }

        $message = $this->messageUtil->getLastUpdateMessage($dc->table, $record);

        if( $message !== null ) {
            $this->framework->getAdapter(Message::class)->addInfo($message);
        }
    }


    /**
     * Strips the protocol and path from the entered domain
     *
     * @param mixed $value
     *
     * @return string
     */
    #[AsCallback('tl_content', target: 'fields.avalex_domain.save')]
    #[AsCallback('tl_module', target: 'fields.avalex_domain.save')]
    public function normalizeDomain( mixed $value ): string {

        $domain = preg_replace('~^([a-z][a-z0-9+.-]*:)?//~i', '', trim((string) $value));

        return explode('/', $domain, 2)[0];
    }


    /**
     * Fetches the texts after saving a record, this also validates
     * the entered domain and API key
     *
     * @param \Contao\DataContainer $dc
     */
    #[AsCallback('tl_content', target: 'config.onsubmit')]
    #[AsCallback('tl_module', target: 'config.onsubmit')]
    public function updateTexts( DataContainer $dc ): void {

        $record = $this->avalexUtil->find($dc->table, (int) $dc->id);

        if( !$record || !AvalexUtil::isConfigured($record) ) {
            return;
        }

        // no need to hit the API again if the texts of this domain and API key are up to date
        if( $this->avalexUtil->getLastFailure($record) === null && !$this->avalexUtil->needsUpdate($record) ) {
            return;
        }

        $message = $this->framework->getAdapter(Message::class);

        try {

            $this->avalexUtil->update($dc->table, $record);
            $message->addConfirmation($this->messageUtil->getSuccessMessage());

        } catch( AvalexApiException $e ) {

            $this->avalexUtil->logFailedUpdate($dc->table, $record, $e);
            $message->addError($this->messageUtil->getErrorMessage($e, $record['type']));
        }
    }
}
