<?php

namespace Icinga\Module\Helpline;

use Icinga\Module\Monitoring\Backend;
use Icinga\Module\Monitoring\Command\Object\AcknowledgeProblemCommand;
use Icinga\Module\Monitoring\Command\Transport\CommandTransport;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Exception\IcingaException;

class IcingaCommandPipe
{
    public function acknowledge($author, $message, $host, $service = null)
    {
        $cmd = new AcknowledgeProblemCommand();
        $cmd->setObject($this->getObject($host, $service))
            ->setAuthor($author)
            ->setComment($message)
            ->setPersistent(false)
            ->setSticky(false)
            ->setNotify(false)
            ;

        $transport = new CommandTransport();
        $transport->send($cmd);
    }

    protected function getObject($hostname, $service)
    {
        if ($service === null) {
            return $this->getHostObject($hostname);
        } else {
            return $this->getServiceObject($hostname, $service);
        }
    }

    protected function getHostObject($hostname)
    {
        $backend = Backend::instance();
        $found = $backend->select()
            ->from('hostStatus')
            ->where('host_name', $hostname)
            ->count();

        if ($found !== 1) {
            throw new IcingaException('No such host found: %s', $hostname);
        }

        $host = new Host($backend, $hostname);

        return $host;
    }

    protected function getServiceObject($hostname, $service)
    {
        $backend = Backend::instance();
        $found = $backend->select()
            ->from('serviceStatus')
            ->where('host_name', $hostname)
            ->where('service_description', $service)
            ->count();

        if ($found !== 1) {
            throw new IcingaException(
                'No service "%s" found on host "%s"',
                $service,
                $hostname
            );
        }

        $service = new Service($backend, $hostname, $service);

        return $service;
    }
}
