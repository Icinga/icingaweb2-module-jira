<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Jira\Forms\Config\TemplateConfigForm;
use Icinga\Module\Jira\Web\Controller;
use Icinga\Module\Jira\Web\Table\JiraCustomFields;
use Icinga\Module\Jira\Web\Table\TemplateTable;
use Icinga\Web\Notification;
use Icinga\Web\Widget\Tab;
use Icinga\Web\Widget\Tabs;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;

class TemplatesController extends Controller
{
    public function init()
    {
        $this->assertPermission('config/modules');
        parent::init();
    }

    public function indexAction()
    {
        $this->mergeTabs($this->Module()->getConfigTabs()->activate('templates'));

        $this->addControl(
            (new ButtonLink(
                t('Add Template'),
                'jira/templates/add',
                'plus'
            ))->setBaseTarget('_next')
        );

        $config = Config::module('jira', 'templates');

        $this->addContent(
            new TemplateTable($config->toArray())
        );
    }

    public function addAction()
    {
        $this->addTitleTab(t('Add Template'));
        $form = (new TemplateConfigForm())
            ->on(TemplateConfigForm::ON_SUCCESS, function ($form) {
                Notification::success(sprintf(
                    t('Created template "%s" successfully'),
                    $form->getValue('template')
                ));
                $this->redirectNow(Url::fromPath('jira/templates'));
            })->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }

    public function editAction()
    {
        $this->addTitleTab(t('Edit Template'));
        $templateName = $this->params->get('template');

        $form = (new TemplateConfigForm($templateName))
            ->on(TemplateConfigForm::ON_SUCCESS, function ($form) use ($templateName) {
                if ($form->getPressedSubmitElement()->getName() === 'delete') {
                    Notification::success(sprintf(t('Template "%s" has been deleted'), $templateName));
                } else {
                    Notification::success(sprintf(t('Template "%s" has been updated'), $templateName));
                }
                
                $this->redirectNow(Url::fromPath('jira/templates'));
            })->handleRequest($this->getServerRequest());

        $this->addContent($form);

        $this->addContent(
            (new ButtonLink(
                t('Add Custom Field'),
                Url::fromPath('jira/fields/add', ['template' => $templateName]),
                'plus'
            ))->setBaseTarget('_next')
        );


        $config = Config::module('jira', 'templates');

        $this->addContent(
            new JiraCustomFields(
                $config->getSection($templateName)->toArray(),
                $templateName,
                $this->jira()
            )
        );
    }

    /**
     * Merge tabs with other tabs in this tab panel
     *
     * @param Tabs $tabs
     *
     * @return void
     */
    protected function mergeTabs(Tabs $tabs): void
    {
        /** @var Tab $tab */
        foreach ($tabs->getTabs() as $tab) {
            $this->getTabs()->add($tab->getName(), $tab);
        }
    }
}
