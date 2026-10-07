<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use numero2\AvalexBundle\Util\AvalexUtil;
use numero2\AvalexBundle\Util\MessageUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


#[AsContentElement('avalex_privacy_policy', category: 'avalex')]
#[AsContentElement('avalex_imprint', category: 'avalex')]
#[AsContentElement('avalex_terms_conditions', category: 'avalex')]
#[AsContentElement('avalex_cancellation_policy', category: 'avalex')]
class AvalexController extends AbstractContentElementController {


    private AvalexUtil $avalexUtil;

    private MessageUtil $messageUtil;


    public function __construct( AvalexUtil $avalexUtil, MessageUtil $messageUtil ) {

        $this->avalexUtil = $avalexUtil;
        $this->messageUtil = $messageUtil;
    }


    /**
     * {@inheritdoc}
     */
    protected function getResponse( FragmentTemplate $template, ContentModel $model, Request $request ): Response {

        $table = ContentModel::getTable();
        $record = $model->row();

        // only show a short status instead of the whole text in the back end preview
        if( $this->isBackendScope($request) ) {

            $template->set('preview', $this->avalexUtil->getLastUpdate($record) === null
                ? $this->messageUtil->getOutdatedMessage($table, $record)
                : $this->messageUtil->getFailedMessage($table, $record) ?? $this->messageUtil->getLastUpdateMessage($table, $record)
            );

            return $template->getResponse();
        }

        $language = $this->getPageModel()?->language ?: $request->getLocale();

        $content = $this->avalexUtil->getOrFetchContent($table, $record, $language);

        if( $content === null ) {
            return new Response('');
        }

        $template->set('content', $content);

        return $template->getResponse();
    }
}
