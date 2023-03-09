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
