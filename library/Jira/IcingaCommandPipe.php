<?php

namespace Icinga\Module\Jira;

use Icinga\Application\Modules\Module;
use Icinga\Module\Jira\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Monitoring\Command\Object\AcknowledgeProblemCommand;
use Icinga\Module\Monitoring\Command\Transport\CommandTransport;
use Icinga\Module\Icingadb\Command\Transport\CommandTransport as IcingadbCommandTransport;
use Icinga\Module\Icingadb\Command\Object\AcknowledgeProblemCommand as IcingadbAcknowledgeProblemCommand;
use Icinga\Exception\IcingaException;
use Icinga\Module\Monitoring\Object\MonitoredObject;

class IcingaCommandPipe
{
    private $monitoringInfo;

    public function acknowledge($author, $message, $host, $service = null)
    {
        if ($this->monitoringInfo === null) {
            if (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend()) {
                $backend = new IcingadbBackend();
            } else {
                $backend = new IdoBackend();
            }

            $this->setMonitoringInfo($backend->getMonitoringInfo($host, $service));
        }

        if (! $this->monitoringInfo->hasObject()) {
            if ($service !== null) {
                throw new IcingaException(
                    'No service "%s" found on host "%s"',
                    $service,
                    $host
                );
            } else {
                throw new IcingaException('No such host found: %s', $host);
            }
        }

        if ($this->monitoringInfo->isAcknowledged()) {
            return false;
        }

        $cmd = $this->getAcknowledgeProblemCommand();
        $cmd->setObject($this->monitoringInfo->getObject())
            ->setAuthor($author)
            ->setComment($message)
            ->setPersistent(false)
            ->setSticky(false)
            ->setNotify(false);

        $transport = $this->getCommandTransport();
        $transport->send($cmd);

        return true;
    }

    public function setMonitoringInfo(MonitoringInfo $info)
    {
        $this->monitoringInfo = $info;

        return $this;
    }

    protected function getAcknowledgeProblemCommand()
    {
        if ($this->monitoringInfo->getObject() instanceof MonitoredObject) {
            return new AcknowledgeProblemCommand();
        }

        return new IcingadbAcknowledgeProblemCommand();
    }

    protected function getCommandTransport()
    {
        if ($this->monitoringInfo->getObject() instanceof MonitoredObject) {
            return new CommandTransport();
        }

        return new IcingadbCommandTransport();
    }
}
