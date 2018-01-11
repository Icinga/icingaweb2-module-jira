<?php

namespace Icinga\Module\Jira\Clicommands;

use Icinga\Module\Helpline\IcingaCommandPipe;
use Icinga\Module\Jira\Cli\Command;

class SendCommand extends Command
{
    public function problemAction()
    {
        $p     = $this->params;
        $host  = $p->getRequired('host');
        $service = $p->get('service');
        $state   = $p->getRequired('state');
        $project     = $p->getRequired('project');
        $issueType   = $p->getRequired('issuetype');
        $summary     = sprintf('%s is %s', $host, $state);
        $description = $p->getRequired('output');

        $fields = [
            'project'       => [ 'key' => $project ],
            'issuetype'     => [ 'name' => $issueType ],
            'summary'       => $summary,
            'description'   => $description,
            'Task'          => 'API',
            'Suchkategorie' => 'CI',
            'Aktivität'     => [ 'value' => 'proaktiv' ],
            'Suche'         => $host,
            'ValCopy'       => $host,
            'icingaStatus'  => $state,
            'icingaHost'    => $host,
        ];

        if ($service !== null) {
            $fields['icingaService'] = $service;
        }

        $jira = $this->jira();
        $key = $jira->eventuallyGetLatestOpenIssueKeyFor($host, $service);
        if ($key === null) {
            $key = $jira->createIssue($fields);
            printf(
                "New JIRA issue %s has been created\n",
                $key
            );
            $message = sprintf(
                'JIRA issue %s has been created',
                $key
            );
        } else {
            $message = sprintf(
                'Existing JIRA issue %s has been found',
                $key
            );
        }

        $cmd = new IcingaCommandPipe();
        $cmd->acknowledge('JIRA', $message, $host, $service);
    }
}
