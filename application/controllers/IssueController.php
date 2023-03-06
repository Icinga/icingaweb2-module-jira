<?php

namespace Icinga\Module\Jira\Controllers;

use Icinga\Module\Jira\Web\Controller;
use Icinga\Module\Jira\Web\Table\IssueDetails;

class IssueController extends Controller
{
    public function showAction()
    {
        $key = $this->params->get('key');
        $issue = $this->runFailSafe(function () use ($key) {
            $issue = $this->jira()->fetchIssue($key);
            $this->addContent(new IssueDetails($issue));
            return $issue;
        });

        $this->addTitleTab('Issue details');
        if ($issue) {
            $this->addTitle('%s: %s', $key, $issue->fields->summary);
        } else {
            $this->addTitle($key);
        }
    }
}
