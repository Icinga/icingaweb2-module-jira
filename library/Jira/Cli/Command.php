<?php

namespace Icinga\Module\Jira\Cli;

use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Jira\RestApi;

class Command extends CliCommand
{
    private $jira;

    protected function jira()
    {
        if ($this->jira === null) {
            $this->jira = $this->connectToJira();
        }

        return $this->jira;
    }

    protected function connectToJira()
    {
        return RestApi::fromConfig();
    }
}
