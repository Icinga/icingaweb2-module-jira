<?php

namespace Icinga\Module\Jira\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Hook\ServiceActionsHook;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;

class ServiceActions extends ServiceActionsHook
{
    public function getActionsForService(Service $service)
    {
        return [
            'JIRA Issues' => Url::fromPath(
                'jira/issues',
                [
                    'host'    => $service->host_name,
                    'service' => $service->service_description,
                    'all'     => true,
                ]
            )
        ];
    }
}
