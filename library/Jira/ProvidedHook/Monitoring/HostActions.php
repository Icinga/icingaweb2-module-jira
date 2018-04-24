<?php

namespace Icinga\Module\Jira\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Web\Url;

class HostActions extends HostActionsHook
{
    /**
     * @param Host $host
     * @return array
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function getActionsForHost(Host $host)
    {
        return [
            'JIRA Issues' => Url::fromPath(
                'jira/issues',
                [
                    'host' => $host->host_name,
                    'all'  => true,
                ]
            )
        ];
    }
}
