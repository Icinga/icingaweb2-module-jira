<?php

namespace Icinga\Module\Jira\Controllers;

use Icinga\Module\Jira\Web\Controller;
use Icinga\Module\Jira\Web\Form;
use Icinga\Module\Jira\Web\Form\NewIssueForm;
use Icinga\Module\Jira\Web\Table\IssuesTable;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Monitoring\Backend;
use Icinga\Web\Notification;
use Icinga\Web\Url;

class IssuesController extends Controller
{
    /**
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\ProgrammingError
     */
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
            $issues = $this->jira()->fetchIssues(
                $host,
                $service,
                ! $showAll
            );
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

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function showNewIssueForm()
    {
        $host = $this->params->getRequired('host');
        $service = $this->params->get('service');

        $this->addTitle(
            $this->translate('Create JIRA Issue') . $this->titleSuffix($host, $service)
        )->activateTab();

        $params = ['host' => $host];
        if ($service) {
            $params['service'] = $service;
            $object = new Service(Backend::instance(), $host, $service);
        } else {
            $object = new Host(Backend::instance(), $host);
        }
        $object->fetch();

        $form = new NewIssueForm();
        $form->setJira($this->jira())
            ->setObject($object)
            ->on(Form::ON_SUCCESS, function (NewIssueForm $form) use ($params) {
                $form->createIssue();
                Notification::success('A new incident has been created');
                $this->redirectNow(Url::fromPath('jira/issues', $params));
            })
            ->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }

    /**
     * @param null $name
     * @return $this
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\ProgrammingError
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
