<?php

/* Icinga Graphite Web | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Jira;

use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use ipl\Stdlib\Filter;

class IcingadbBackend
{
    use Database;
    use Auth;

    /**
     * @param string $hostName
     *
     * @param string|null $serviceName
     *
     * @return Host|Service
     */
    protected function getObject($hostName, $serviceName = null)
    {
        if ($serviceName ===  null) {
            $query = Host::on($this->getDb())->with(['state', 'icon_image']);
            $query->setResultSetClass(VolatileStateResults::class);
            $query->filter(
                Filter::equal('host.name', $hostName)
            );

            $this->applyRestrictions($query);
            $object = $query->first();
        } else {
            $query = Service::on($this->getDb())->with([
                'state',
                'icon_image',
                'host',
                'host.state'
            ]);
            $query->setResultSetClass(VolatileStateResults::class);

            $query->filter(Filter::all(
                Filter::equal('service.name', $serviceName),
                Filter::equal('host.name', $hostName)
            ));

            $this->applyRestrictions($query);
            $object =  $query->first();
        }

         if ($object === null) {
             if ($serviceName === null) {
                $object = (new Host())->setProperties($this->getObjectProperties($hostName));
             } else {
                 $object = (new Service())->setProperties($this->getObjectProperties($hostName, $serviceName));
             }
         }


        return $object;
    }

    protected function getObjectProperties($hostName, $serviceName = null)
    {
        $props = [
            'is_empty'  => 'true',      // to check, that no object was found
            'name'      => $hostName
        ];

        if ($serviceName !== null) {
            $props['name']          = $serviceName;
            $props['host.name']     = $hostName;
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
