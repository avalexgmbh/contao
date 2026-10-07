<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use numero2\AvalexBundle\Util\AvalexUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


#[AsFrontendModule('avalex_privacy_policy', category: 'avalex')]
#[AsFrontendModule('avalex_imprint', category: 'avalex')]
#[AsFrontendModule('avalex_terms_conditions', category: 'avalex')]
#[AsFrontendModule('avalex_cancellation_policy', category: 'avalex')]
class AvalexController extends AbstractFrontendModuleController {


    private AvalexUtil $avalexUtil;


    public function __construct( AvalexUtil $avalexUtil ) {

        $this->avalexUtil = $avalexUtil;
    }


    /**
     * {@inheritdoc}
     */
    protected function getResponse( FragmentTemplate $template, ModuleModel $model, Request $request ): Response {

        $language = $this->getPageModel()?->language ?: $request->getLocale();

        $content = $this->avalexUtil->getOrFetchContent(ModuleModel::getTable(), $model->row(), $language);

        if( $content === null ) {
            return new Response('');
        }

        $template->set('content', $content);

        return $template->getResponse();
    }
}
