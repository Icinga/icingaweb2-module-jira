<?php

namespace Icinga\Module\Jira\Controllers;

use Icinga\Module\Jira\Web\Controller;
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
            $issues = $this->jira()->fetchIssues(
                $this->params->get('host'),
                $this->params->get('service'),
                ! $this->params->get('all')
            );
            $this->content()->add(new IssuesTable($issues));
        });
    }
}
