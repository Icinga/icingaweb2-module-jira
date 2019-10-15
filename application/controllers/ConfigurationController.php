<?php

namespace Icinga\Module\Jira\Controllers;

use gipfl\IcingaWeb2\Link;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Jira\DirectorConfig;
use Icinga\Module\Jira\Web\Controller;
use Icinga\Module\Jira\Web\Form\TemplateForm;
use Icinga\Web\Notification;
use ipl\Html\Html;

class ConfigurationController extends Controller
{
    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function init()
    {
        $this->assertPermission('director/admin');
    }

    public function inspectAction()
    {
        $this->addTitle('JIRA Inspection')->activateTab();
        $this->content()->add(Html::tag('div', ['class' => 'state-hint warning'], $this->translate(
            'This page serves no special purpose right now, but gives some insight'
            . ' into available projects and Custom Fields'
        )));
        $this->runFailSafe(function () {
            $this->content()->add(new TemplateForm($this->jira()));
        });
    }

    public function directorAction()
    {
        $this->addTitle('Director Config Preview')->activateTab();
        if ($this->params->get('action') === 'sync') {
            $this->runFailSafe('sync');
            return;
        }
        $this->actions()->add(Link::create(
            'Sync to Director',
            'jira/configuration/director',
            ['action' => 'sync'],
            ['class'  => 'icon-flapping']
        ));
        $this->runFailSafe(function () {
            try {
                $config = new DirectorConfig();
                $this->addCommand($config->createHostCommand(), $config);
                $this->addCommand($config->createServiceCommand(), $config);
            } catch (ConfigurationError $e) {
                $this->content()->add([
                    Html::tag('h1', ['class' => 'state-hint error'], $this->translate(
                        'Icinga Director has not been configured on this system: %s',
                        $e->getMessage()
                    ))
                ]);
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
        $this->redirectNow($this->url()->without('action'));
    }

    /**
     * @param IcingaCommand $command
     * @param DirectorConfig $config
     */
    protected function addCommand(IcingaCommand $command, DirectorConfig $config)
    {
        $c = $this->content();
        $name = $command->getObjectName();
        $c->add(Html::tag('h1', null, $name));
        if ($config->commandExists($command)) {
            $link = Link::create(
                $name,
                'director/command',
                ['name' => $name],
                ['data-base-target' => '_next']
            );

            if ($config->commandDiffers($command)) {
                $c->add($this->createHint(
                    Html::sprintf(
                        'The CheckCommand %s exists but differs in your Icinga Director',
                        $link
                    ),
                    'warning'
                ));
            } else {
                $c->add($this->createHint(
                    Html::sprintf(
                        'The CheckCommand definition for %s is fine',
                        $link
                    ),
                    'ok'
                ));
            }
        } else {
            $c->add($this->createHint(
                'Command does not exist in your Icinga Director',
                'warning'
            ));
        }
        $c->add(Html::tag('pre', null, (string) $command));
    }

    protected function createHint($msg, $state)
    {
        return Html::tag('p', ['class' => ['state-hint', $state]], $msg);
    }

    /**
     * @param null $name
     * @return $this
     */
    protected function activateTab($name = null)
    {
        if ($name === null) {
            $name = $this->getRequest()->getActionName();
        }
        $this->tabs()->add('director', [
            'label' => $this->translate('Director Config'),
            'url' => 'jira/configuration/director',
        ])->add('inspect', [
            'label' => $this->translate('Inspect'),
            'url' => 'jira/configuration/inspect',
        ])->activate($name);
        
        return $this;
    }
}
