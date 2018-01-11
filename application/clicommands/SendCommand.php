<?php

namespace Icinga\Module\Jira\Clicommands;

use Icinga\Module\Jira\Cli\Command;

class SendCommand extends Command
{
    public function problemAction()
    {
        $p     = $this->params;
        $host  = $p->getRequired('host');
        $state = $p->getRequired('state');
        $project     = $p->getRequired('project');
        $issueType   = $p->getRequired('issuetype');
        $summary     = sprintf('%s is %s', $host, $state);
        $description = $p->getRequired('output');

        $fields = [
            'project'       => [ 'key' => $project ],
            'issuetype'     => [ 'name' => $issueType ],
            'summary'       => $summary,
            'description'   => $description,
            'icingaStatus'  => $state,
            'icingaHost'    => $host,
        ];

        printf(
            "New JIRA issue %s has been created\n",
            $this->jira()->createIssue($fields)
        );
    }
}
