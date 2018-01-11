<?php

namespace Icinga\Module\Jira\Controllers;

use dipl\Html\Html;
use Icinga\Module\Jira\Web\Controller;
use Icinga\Module\Jira\Web\Form\TemplateForm;
use Icinga\Module\Jira\Web\Table\IssuesTable;

class IssuesController extends Controller
{
    public function indexAction()
    {
        $this
            ->addTitle('Ticket Search')
            ->addSingleTab('Issues')
            ->setAutorefreshInterval(60);

        $this->runFailSafe(function () {
            $this->content()->add(new IssuesTable($this->fetchIssues()));
        });
    }

    protected function fetchIssues()
    {
        $start = 0;
        $limit = 15;
        $project = 'ITSM';
        $host = $this->params->get('host');
        $onlyOpen = ! $this->params->get('all');

        $query = sprintf('project = "%s" AND creator = currentUser()', $project);

        if ($onlyOpen) {
            $query .= ' AND status NOT IN (Gelöst, Geschlossen, Abgelehnt)';
        }

        if ($host !== null) {
            $query .= sprintf(' AND icingaHost ~ "%s"', $host);
        }

        $query .= ' ORDER BY created DESC';
        $fields = [
            'project',
            'issuetype',
            'description',
            'summary',
            'status',
            'created',
            'icingaStatus',
            'icingaHost',
            'icingaService',
        ];

        return $this->jira()->post('search', [
            'jql'        => $query,
            'startAt'    => $start,
            'maxResults' => $limit,
            'fields'     => $fields,
        ])->getResult()->issues;
    }
}
