<?php

namespace Icinga\Module\Jira;

class IcingaIssue
{
    protected $params;

    public function __construct($project, $issueType, $state, $host, $service, $params = [])
    {
        $this->params = [
            'project'   => $project,
            'issueType' => $issueType,
            'state'     => $state,
            'host'      => $host,
            'service'   => $service,
        ] + $params;
    }

    public function send(RestApi $jira, IssueTemplate $template)
    {
        $jira->post('issue', [
            'fields' => $template->getFilled($this->params)
        ]);
    }
}
