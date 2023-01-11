<?php

namespace Icinga\Module\Jira;

use Icinga\Date\DateFormatter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Icingadb\Model\CustomvarFlat;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Module\Icingadb\Model\Host as IcingadbHost;
use Icinga\Module\Icingadb\Model\Service as IcingadbService;
use Exception;
use InvalidArgumentException;

class MonitoringInfo
{
    /** @var MonitoredObject|IcingadbHost|IcingadbService */
    protected $object;

    /** @var string */
    protected $notificationType;

    protected $vars;

    protected $fetched;

    /**
     * @throws IcingaException  When the given object does not belong to the expected classes
     */
    public function __construct($object)
    {
        if (! $object instanceof MonitoredObject
            && ! $object instanceof IcingadbHost
            && ! $object instanceof IcingadbService) {
            throw new InvalidArgumentException(sprintf(
                'Expects the given object to be an instance of %s, %s or %s: got %s',
                MonitoredObject::class,
                IcingadbHost::class,
                IcingadbService::class,
                get_class($object)
            ));
        }

        $this->object = $object;
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
            }

            return null;
        }

        $name = str_replace('.', '_', $name);
        try {
            return $this->object->$name;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getHostname()
    {
        if ($this->object instanceof MonitoredObject) {
            return $this->object->host_name;
        }

        if ($this->object instanceof IcingadbService) {
            return $this->object->host->name;
        }

        return $this->object->name;
    }

    public function getService()
    {
        if ($this->object instanceof Service) {
            return $this->object->service;
        }

        if ($this->object instanceof IcingadbService) {
            return $this->object->name;
        }

        return null;
    }

    public function getStateName()
    {
        if ($this->object instanceof MonitoredObject) {
            if ($this->object instanceof Service) {
                return strtoupper(Service::getStateText($this->object->service_state));
            }

            return strtoupper(Host::getStateText($this->object->host_state));
        }

        return strtoupper($this->object->state->getStateText());
    }

    public function getLastStateChange()
    {
        if ($this->object instanceof MonitoredObject) {
            return DateFormatter::formatDateTime($this->object->last_state_change);
        }

        return DateFormatter::formatDateTime($this->object->state->last_state_change);
    }

    public function getOutput()
    {
        if ($this->object instanceof MonitoredObject) {
            if ($this->object instanceof Service) {
                return $this->object->service_output . "\n"
                    . str_replace('\n', "\n", $this->object->service_long_output);
            }

            return $this->object->host_output . "\n"
                . str_replace('\n', "\n", $this->object->host_long_output);
        }

        return $this->object->state->output . "\n"
            . str_replace('\n', "\n", $this->object->state->long_output);
    }

    public function hostVars()
    {
        if ($this->object instanceof MonitoredObject) {
            return $this->object->hostVariables;
        }

        if ($this->object instanceof IcingadbService) {
            return (new CustomvarFlat())->unFlattenVars($this->object->host->customvar_flat);
        }

        return (new CustomvarFlat())->unFlattenVars($this->object->customvar_flat);
    }

    public function serviceVars()
    {
        if ($this->object instanceof Service) {
            return $this->object->serviceVariables;
        }

        if ($this->object instanceof IcingadbService) {
            return (new CustomvarFlat())->unFlattenVars($this->object->customvar_flat);
        }

        return [];
    }

    public function getObjectParams()
    {
        $params = ['host' => $this->getHostname()];
        if ($this->getService() !== null) {
            $params['service'] = $this->getService();
        }

        return $params;
    }

    public function getObjectLabel()
    {
        if ($this->getService() === null) {
            return $this->getHostname();
        }

        return sprintf(
            '%s on %s',
            $this->getService(),
            $this->getHostname()
        );
    }

    public function getDefaultSummary()
    {
        return sprintf('%s is %s', $this->getObjectLabel(), $this->getStateName());
    }

    public function getDescriptionHeader()
    {
        if ($this->notificationType) {
            $description = sprintf("Notification Type: %s\n", rawurlencode($this->notificationType));
        } else {
            $description = '';
        }

        $description .= sprintf("Last state change: %s\n", $this->getLastStateChange());

        $hostLink = $this->object instanceof MonitoredObject
            ? LinkHelper::linkToIcingaHost($this->getHostname())
            : LinkHelper::linkToIcingadbHost($this->getHostname());

        if ($this->getService() !== null) {
            $serviceLink = $this->object instanceof MonitoredObject
                ? LinkHelper::linkToIcingaService($this->getHostname(), $this->getService())
                : LinkHelper::linkToIcingadbService($this->getHostname(), $this->getService());

            $description .= sprintf("Service: %s\n", $serviceLink);
        }

        $description .= sprintf("Host: %s\n", $hostLink);

        return $description;
    }

    public function isAcknowledged()
    {
        return $this->object instanceof MonitoredObject
            ? $this->object->acknowledged
            : $this->object->state->is_acknowledged;
    }

    public function hasObject()
    {
        if ($this->object instanceof MonitoredObject) {
            return $this->object->fetch() !== false;
        }

        return $this->object->hasProperty('is_empty') === false;
    }

    /**
     * @return IcingadbHost|IcingadbService|MonitoredObject
     */
    public function getObject()
    {
        return $this->object;
    }
}
