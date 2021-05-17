<?php

namespace Icinga\Module\Jira\Controllers;

use Icinga\Module\Jira\MonitoringInfo;
use Icinga\Module\Jira\Web\Controller;
use Icinga\Module\Jira\Web\Form;
use Icinga\Module\Jira\Web\Form\NewIssueForm;
use Icinga\Module\Jira\Web\Table\IssuesTable;
use Icinga\Web\Notification;
use Icinga\Web\Url;

class IssuesController extends Controller
{
    public function indexAction()
    {
        $host = $this->params->get('host');
        $service = $this->params->get('service');
        $showAll = $this->params->get('all');

        $title = $this->translate('Ticket Search') . $this->titleSuffix($host, $service);

        if ($showAll) {
            $title .= sprintf(' (%s)', $this->translate('with closed ones'));
        }

        $this->addTitle($title)->activateTab()->setAutorefreshInterval(60);

        $this->runFailSafe(function () use ($host, $service, $showAll) {
            $issues = $this->jira()->fetchIssues($host, $service, ! $showAll);
            if (empty($issues)) {
                $this->content()->add($this->translate('No issue found'));
            } else {
                $this->content()->add(new IssuesTable($issues));
            }
        });
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function createAction()
    {
        $this->assertPermission('jira/issue/create');
        $this->runFailSafe('showNewIssueForm');
    }

    protected function showNewIssueForm()
    {
        $info = $this->requireMonitoringInfo();
        $this->addTitle($this->translate('Create JIRA Issue') . ': ' . $info->getObjectLabel())
            ->activateTab();

        $form = (new NewIssueForm($this->jira(), $this->getModuleConfig(), $info))
            ->on(Form::ON_SUCCESS, function (NewIssueForm $form) use ($info) {
                $form->createIssue();
                Notification::success('A new incident has been created');
                $this->redirectNow(Url::fromPath('jira/issues', $info->getObjectParams()));
            })
            ->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }

    protected function requireMonitoringInfo()
    {
        return new MonitoringInfo($this->params->getRequired('host'), $this->params->get('service'));
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
        $tabs = $this->tabs();

        $params = [];
        foreach (['host', 'service'] as $param) {
            if ($value = $this->params->get($param)) {
                $params[$param] = $value;
            }
        }

        $tabs->add('index', [
            'label'     => $this->translate('Issues'),
            'url'       => 'jira/issues',
            'urlParams' => $params,
        ]);

        if ($this->hasPermission('jira/issue/create') && $this->params->has('host')) {
            $tabs->add('create', [
                'label'     => $this->translate('Create'),
                'url'       => 'jira/issues/create',
                'urlParams' => $params,
            ]);
        }
        $tabs->activate($name);

        return $this;
    }

    protected function titleSuffix($host, $service)
    {
        if ($host === null) {
            return '';
        } else {
            if ($service) {
                return ": $service on $host";
            } else {
                return ": $host";
            }
        }
    }
}
