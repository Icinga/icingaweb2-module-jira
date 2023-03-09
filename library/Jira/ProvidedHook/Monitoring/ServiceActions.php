<?php

namespace Icinga\Module\Jira\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Hook\ServiceActionsHook;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;

class ServiceActions extends ServiceActionsHook
{
    /**
     * @param Service $service
     * @return array
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function getActionsForService(Service $service)
    {
        return [
            'Jira Issues' => Url::fromPath(
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
