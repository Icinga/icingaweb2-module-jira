<?php

/* Icinga Web Jira Module | (c) 2021 Icinga GmbH | GPLv2*/

namespace Icinga\Module\Jira\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\HostActionsHook;
use Icinga\Module\Icingadb\Model\Host;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class HostActions extends HostActionsHook
{
    public function getActionsForObject(Host $host): array
    {
        return [
            new Link(
                'JIRA Issues',
                Url::fromPath(
                    'jira/issues',
                    [
                        'host'  => $host->name,
                        'all'   => true
                    ]
                )
            )
        ];
    }
}
