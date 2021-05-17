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

    /** @var string */
    protected $notificationType;

    protected $vars;

    protected $fetched;

    public function __construct($hostName, $serviceName = null)
    {
        $this->hostName = $hostName;
        $this->serviceName = $serviceName;
    }

    public function setNotificationType($type)
    {
        $this->notificationType = $type;

        return $this;
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

    public function getHostname()
    {
        return $this->object()->host_name;
    }

    public function getService()
    {
        $object = $this->object();
        if ($object instanceof Service) {
            return $object->service_description;
        } else {
            return $object->host_name;
        }
    }

    public function getStateName()
    {
        $object = $this->object();
        if ($object instanceof Service) {
            return strtoupper(Service::getStateText($object->service_state));
        } else {
            return strtoupper(Host::getStateText($object->host_state));
        }
    }

    public function getOutput()
    {
        $object = $this->object();
        if ($object instanceof Service) {
            return $object->service_output . "\n"
                . str_replace('\n', "\n", $object->service_long_output);
        } else {
            return $object->host_output . "\n"
                . str_replace('\n', "\n", $object->host_long_output);
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

    public function getObjectParams()
    {
        $object = $this->object();
        $params = ['host' => $object->host_name];
        if ($object instanceof Service) {
            $params['service'] = $object->service_description;
        }

        return $params;
    }

    public function getObjectLabel()
    {
        $object = $this->object();
        if ($object instanceof Service) {
            return sprintf(
                '%s on %s',
                $object->service_description,
                $object->host_name
            );
        }

        return $object->host_name;
    }

    public function getDefaultSummary()
    {
        return sprintf('%s is %s', $this->getObjectLabel(), $this->getStateName());
    }

    public function getDescriptionHeader()
    {
        $object = $this->object();
        if ($this->notificationType) {
            $description = sprintf("Notification Type: %s\n", rawurlencode($this->notificationType));
        } else {
            $description = '';
        }

        if ($object->getType() === 'service') {
            $description .= sprintf(
                "Service: %s\n",
                LinkHelper::linkToIcingaService($object->host_name, $object->service_description)
            );
        }
        $description .= sprintf(
            "Host: %s\n",
            LinkHelper::linkToIcingaHost($object->host_name)
        );

        return $description;
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
