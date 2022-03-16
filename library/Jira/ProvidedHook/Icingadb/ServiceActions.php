<?php

/* Icinga Web Jira Module | (c) 2021 Icinga GmbH | GPLv2*/

namespace Icinga\Module\Jira\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\ServiceActionsHook;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class ServiceActions extends ServiceActionsHook
{
    public function getActionsForObject(Service $service): array
    {
         return [
             new Link(
                 'JIRA Issues',
                 Url::fromPath(
                     'jira/issues',
                     [
                         'service'  => $service->name,
                         'host'     => $service->host->name,
                         'all'      => true
                     ]
                 )
             )
        ];
    }
}
