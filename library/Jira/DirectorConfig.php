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
            'arguments'   => $this->defaultHostArguments(),
            'vars'        => $this->defaultHostVars(),
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
            'arguments'   => $this->defaultServiceArguments() + $this->defaultHostArguments(),
            'vars'        => $this->defaultServiceVars(),
        ], $this->db());
    }

    protected function requiredArguments()
    {
        return [
            '--project'   => (object) [
                'value'       => '$jira_project$',
                'required'    => true,
                'description' => 'JIRA project name (e.g. ITSM)',
            ],
            '--issuetype' => (object) [
                'value'       => '$jira_issuetype$',
                'description' => 'JIRA issue type (e.g. Incident)',
                'required'    => true,
            ],
            '--summary' => (object) [
                'value'       => '$jira_summary$',
                'description' => 'JIRA issue summary',
                'required'    => true,
            ],
            '--description' => (object) [
                'value'       => '$jira_description$',
                'description' => 'JIRA issue description',
                'required' => true,
            ],
            '--template' => (object) [
                'value'       => '$jira_template$',
                'description' => 'Issue template name (templates.ini section).'
                    . ' This allows to pass custom fields to JIRA',
            ],
            '--ack-author' => (object) [
                'value'       => '$jira_ack_author$',
                'description' => 'This author name will be used when acknowledging'
                    . ' Icinga problems once a JIRA issue got created',
            ],
            '--no-acknowledge' => (object) [
                'value'       => '$jira_no_acknowledge$',
                'description' => 'D not acknowledge  Icinga problems once a JIRA'
                    . ' issue got created',
            ],
            '--command-pipe' => (object) [
                'value'       => '$jira_command_pipe$',
                'description' => 'Legacy Icinga command pipe. Should only be'
                    . ' used on Icinga 1.x system without a correctly configured'
                    . ' Icinga Web 2 monitoring module',
            ],
        ];
    }

    protected function defaultHostArguments()
    {
        return [
            '--host'  => '$host.name$',
            '--state' => (object) [
                'value'       => '$host.state$',
                'description' => 'Host state (e.g. DOWN)',
            ],
        ] + $this->requiredArguments();
    }

    protected function defaultServiceArguments()
    {
        return [
            '--service' => '$service.name$',
            '--state' => (object) [
                'value'       => '$service.state$',
                'description' => 'Service state (e.g. CRITICAL)',
            ],
        ];
    }

    protected function defaultHostVars()
    {
        return [
            'jira_description' => '$host.output$',
            'jira_summary'     => '$host.name$ is $host.state$',
        ];
    }

    protected function defaultServiceVars()
    {
        return [
            'jira_description' => '$service.output$',
            'jira_summary'     => '$service.name$ on $host.name$ is $service.state$',
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
