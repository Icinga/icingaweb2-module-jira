<?php

namespace Icinga\Module\Jira;

use Icinga\Module\Monitoring\Backend;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;
use Exception;

class MonitoringInfo
{
    /** @var string */
    protected $hostName;

    /** @var string|null */
    protected $serviceName;

    /** @var MonitoredObject */
    protected $object;

    protected $vars;

    protected $fetched;

    public function __construct($hostName, $serviceName = null)
    {
        $this->hostName = $hostName;
        $this->serviceName = $serviceName;
    }

    public function getProperty($name)
    {
        if (preg_match('/^(.+)\.vars\.(.+)$/', $name, $matches)) {
            $type = $matches[1];
            $varName = $matches[2];
            switch ($type) {
                case 'host':
                    $vars = $this->hostVars();
                    break;
                case 'service':
                    $vars = $this->serviceVars();
                    break;
                default:
                    $vars = [];
            }

            if (isset($vars[$varName])) {
                return $vars[$varName];
            } else {
                return null;
            }
        } else {
            $name = str_replace('.', '_', $name);

            try {
                return $this->object()->$name;
            } catch (Exception $e) {
                return null;
            }
        }
    }

    public function vars()
    {
        return $this->object()->variables;
    }

    public function hostVars()
    {
        return $this->object()->hostVariables;
    }

    public function serviceVars()
    {
        if ($this->object() instanceof Service) {
            return $this->object()->serviceVariables;
        } else {
            return [];
        }
    }

    public function object()
    {
        if ($this->object === null) {
            if ($this->serviceName ===  null) {
                $this->object = new Host(Backend::instance(), $this->hostName);
            } else {
                $this->object = new Service(Backend::instance(), $this->hostName, $this->serviceName);
            }

            $this->object->fetch();
        }

        return $this->object;
    }
}
