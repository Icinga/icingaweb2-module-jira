<?php

namespace Icinga\Module\Jira;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaCommand;

class DirectorConfig
{
    /** @var Db */
    protected $db;

    public function commandExists(IcingaCommand $command)
    {
        return IcingaCommand::exists($command->getObjectName(), $this->db);
    }

    public function commandDiffers(IcingaCommand $command)
    {
        return IcingaCommand::load($command->getObjectName(), $this->db)
            ->replaceWith($command)
            ->hasBeenModified();
    }

    public function sync()
    {
        $host = $this->syncCommand($this->createHostCommand());
        $service = $this->syncCommand($this->createServiceCommand());

        return $host || $service;
    }

    public function syncCommand(IcingaCommand $command)
    {
        $db = $this->db;

        $name = $command->getObjectName();
        if ($command::exists($name, $db)) {
            $new = $command::load($name, $db)
                ->replaceWith($command);
            if ($new->hasBeenModified()) {
                $new->store();

                return true;
            } else {
                return false;
            }
        } else {
            $command->store($db);

            return true;
        }
    }

    /**
     * @return IcingaCommand
     */
    public function createHostCommand()
    {
        return IcingaCommand::create([
            'methods_execute' => 'PluginNotification',
            'object_name' => 'JIRA Host Notification',
            'object_type' => 'object',
            'command'     => '/usr/bin/icingacli jira send problem',
            'arguments'   => $this->defaultHostArguments()
        ], $this->db());
    }

    /**
     * @return IcingaCommand
     */
    public function createServiceCommand()
    {
        return IcingaCommand::create([
            'methods_execute' => 'PluginNotification',
            'object_name' => 'JIRA Service Notification',
            'object_type' => 'object',
            'command'     => '/usr/bin/icingacli jira send problem',
            'arguments'   => $this->defaultServiceArguments() + $this->defaultHostArguments()
        ], $this->db());
    }

    protected function requiredArguments()
    {
        return [
            '--project'   => (object) [
                'value'    => '$jira_project$',
                'required' => true,
            ],
            '--issuetype' => (object) [
                'value'    => '$jira_issuetype$',
                'required' => true,
            ],
        ];
    }

    protected function defaultHostArguments()
    {
        return [
            '--host'       => '$host.name$',
            '--host-alias' => '$host.display_name$',
            '--output'     => '$host.output$',
            '--state'      => '$host.state$',
        ] + $this->requiredArguments();
    }

    protected function defaultServiceArguments()
    {
        return [
            '--service' => '$service.name$',
            '--output'  => '$service.output$',
            '--state'   => '$service.state$',
        ];
    }

    public function db()
    {
        if ($this->db === null) {
            $this->db = $this->initializeDb();
        }

        return $this->db;
    }

    protected function initializeDb()
    {
        $resourceName = Config::module('director')->get('db', 'resource');
        return Db::fromResourceName($resourceName);
    }
}
