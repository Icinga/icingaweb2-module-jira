<?php

namespace Icinga\Module\Jira\Controllers;

use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Jira\DirectorConfig;
use Icinga\Module\Jira\Forms\Config\ConfigForm;
use Icinga\Module\Jira\Web\Controller;
use Icinga\Module\Jira\Web\Form\TemplateForm;
use Icinga\Web\Notification;
use Icinga\Web\Widget\Tab;
use Icinga\Web\Widget\Tabs;
use ipl\Html\Html;
use ipl\Web\Url;
use ipl\Web\Widget\ActionLink;
use ipl\Web\Widget\Link;

class ConfigurationController extends Controller
{
    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function init()
    {
        $this->assertPermission('config/modules');
    }

    public function indexAction()
    {
        $this->mergeTabs($this->Module()->getConfigTabs()->activate('deployment'));

        $config = Config::module('jira');

        $form = (new ConfigForm())
            ->populate($config)
            ->on(ConfigForm::ON_SUCCESS, function ($form) use ($config) {
                $config->getConfigObject()->merge($form->getValues());
                $config->saveIni();

                Notification::success(t('Jira configuration has been saved successfully'));
            })->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }

    // For testing purpose only
    public function inspectAction()
    {
        $this->addTitleTab(t('Inspect'));
        $this->addTitle(t('Jira Inspection'));
        $this->addContent(Html::tag('div', ['class' => 'state-hint warning'], $this->translate(
            'This page serves no special purpose right now, but gives some insight'
            . ' into available projects and Custom Fields'
        )));
        $this->runFailSafe(function () {
            $this->addContent(new TemplateForm($this->jira()));
        });
    }

    public function directorAction()
    {
        $this->assertPermission('director/admin');
        $this->mergeTabs($this->Module()->getConfigTabs()->activate('director'));

        if ($this->params->get('action') === 'sync') {
            $this->runFailSafe('sync');
            return;
        }
        $this->addControl(new ActionLink(
            'Sync to Director',
            Url::fromPath('jira/configuration/director', ['action' => 'sync']),
            'sync'
        ));
        $this->runFailSafe(function () {
            try {
                $config = new DirectorConfig();
                $this->addCommand($config->createHostCommand(), $config);
                $this->addCommand($config->createServiceCommand(), $config);
            } catch (ConfigurationError $e) {
                $this->addContent(
                    Html::tag('h1', ['class' => 'state-hint error'], $this->translate(
                        'Icinga Director has not been configured on this system: %s',
                        $e->getMessage()
                    ))
                );
            }
        });
    }

    protected function sync()
    {
        $config = new DirectorConfig();
        if ($config->sync()) {
            Notification::success('Commands have been updated in Icinga Director');
        } else {
            Notification::success('Nothing changed, commands are fine');
        }
        $this->redirectNow($this->getRequest()->getUrl()->without('action'));
    }

    /**
     * @param IcingaCommand $command
     * @param DirectorConfig $config
     */
    protected function addCommand(IcingaCommand $command, DirectorConfig $config)
    {
        $name = $command->getObjectName();
        $this->addContent(Html::tag('h1', null, $name));
        if ($config->commandExists($command)) {
            $link = new Link(
                $name,
                Url::fromPath('director/command', ['name' => $name]),
                ['data-base-target' => '_next']
            );

            if ($config->commandDiffers($command)) {
                $this->addContent($this->createHint(
                    Html::sprintf(
                        'The CheckCommand %s exists but differs in your Icinga Director',
                        $link
                    ),
                    'warning'
                ));
            } else {
                $this->addContent($this->createHint(
                    Html::sprintf(
                        'The CheckCommand definition for %s is fine',
                        $link
                    ),
                    'ok'
                ));
            }
        } else {
            $this->addContent($this->createHint(
                'Command does not exist in your Icinga Director',
                'warning'
            ));
        }
        $this->addContent(Html::tag('pre', null, (string) $command));
    }

    protected function createHint($msg, $state)
    {
        return Html::tag('p', ['class' => ['state-hint', $state]], $msg);
    }

    /**
     * Merge tabs with other tabs contained in this tab panel
     *
     * @param Tabs $tabs
     *
     * @return void
     */
    protected function mergeTabs(Tabs $tabs): void
    {
        /** @var Tab $tab */
        foreach ($tabs->getTabs() as $tab) {
            $this->tabs->add($tab->getName(), $tab);
        }
    }
}
