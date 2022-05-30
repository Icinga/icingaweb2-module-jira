<?php

/* Icinga Graphite Web | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Jira;

use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;

class IdoBackend
{
    /**
     * @param string $hostName
     *
     * @param string|null $serviceName
     *
     * @return MonitoredObject
     */
    protected function getObject($hostName, $serviceName = null)
    {
        if ($serviceName ===  null) {
            $object = new Host(MonitoringBackend::instance(), $hostName);
        } else {
            $object = new Service(MonitoringBackend::instance(), $hostName, $serviceName);
        }

        if (! $object->fetch()) {
            $object->setProperties((object) $this->getObjectProperties($hostName, $serviceName));
        }

        return $object;
    }

    protected function getObjectProperties($hostName, $serviceName = null)
    {
        $props = ['host_name' => $hostName];
        if ($serviceName !== null) {
            $props['service_description'] = $serviceName;
        }

        return $props;
    }

    /**
     * @param string $hostName
     *
     * @param string|null $serviceName
     *
     * @return MonitoringInfo
     */
    public function getMonitoringInfo($hostName, $serviceName = null)
    {
        return new MonitoringInfo($this->getObject($hostName, $serviceName));
    }
}
