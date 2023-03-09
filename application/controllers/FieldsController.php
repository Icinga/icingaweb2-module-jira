<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Jira\Forms\Config\FieldConfigForm;
use Icinga\Module\Jira\Web\Controller;
use Icinga\Module\Jira\Web\Table\JiraCustomFields;
use Icinga\Web\Notification;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;

class FieldsController extends Controller
{
    /** @var string Template Name */
    private $template;

    public function init()
    {
        $this->assertPermission('config/modules');
        $this->template = $this->params->getRequired('template');
        parent::init();
    }

    public function indexAction()
    {
        $this->createTemplateTabs($this->template)->activate('fields');

        $this->addContent(
            (new ButtonLink(
                t('Add Custom Field'),
                Url::fromPath(
                    'jira/fields/add',
                    ['template' => $this->template]
                ),
                'plus'
            ))->setBaseTarget('_next')
        );

        $config = Config::module('jira', 'templates');

        $this->addContent(
            new JiraCustomFields(
                $config->getSection($this->template)->toArray(),
                $this->template,
                $this->jira()
            )
        );
    }

    public function addAction()
    {
        $this->addTitleTab(t('Add Custom Field'));

        $form = (new FieldConfigForm($this->jira(), $this->template))
            ->on(FieldConfigForm::ON_SUCCESS, function ($form) {
                Notification::success(sprintf(
                    t('Added custom field %s successfully to the template %s'),
                    $form->enumAllowedFields()[$form->getValue('fields')],
                    $this->template
                ));
                $this->redirectNow(Url::fromPath('jira/fields', ['template' => $this->template]));
            })->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }
}
