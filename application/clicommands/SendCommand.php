<?php

namespace Icinga\Module\Jira\Clicommands;

use Exception;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use Icinga\Application\Config;
use Icinga\Exception\IcingaException;
use Icinga\Module\Jira\IcingaCommandPipe;
use Icinga\Module\Jira\Cli\Command;
use Icinga\Module\Jira\IcingadbBackend;
use Icinga\Module\Jira\IdoBackend;
use Icinga\Module\Jira\IssueTemplate;
use Icinga\Module\Jira\IssueUpdate;
use Icinga\Module\Jira\LegacyCommandPipe;

class SendCommand extends Command
{
    /**
     * Create an issue for the given Host or Service problem
     *
     * Use this as a NotificationCommand for Icinga
     *
     * USAGE
     *
     * icingacli jira send problem [options]
     *
     * REQUIRED OPTIONS
     *
     *   --project <project-name>     Jira project name, like "ITSM"
     *   --issuetype <type-name>      Jira issue type, like "Incident"
     *   --summary <summary>          Jira issue summary
     *   --description <description>  Jira issue description text
     *   --state <state-name>         Icinga state
     *   --host <host-name>           Icinga Host name
     *
     * OPTIONAL
     *
     *   --service <service-name>   Icinga Service name
     *   --due-date <due-date>        When the Jira ticket is to be due.
     *                              It can be a date  or time difference in textual datetime format.
     *   --template <template-name> Template name (templates.ini section)
     *   --ack-author <author>      Username shown for acknowledgements,
     *                              defaults to "Jira"
     *   --no-acknowledge           Do not acknowledge Icinga problem
     *   --command-pipe <path>      Legacy command pipe, allows to run without
     *                              depending on a configured monitoring module
     *   --icingadb                 Use icingadb as backend, the IDO backend (if available)
     *                              is used if not passed
     *
     * FLAGS
     *   --verbose    More log information
     *   --trace      Get a full stack trace in case an error occurs
     *   --benchmark  Show timing and memory usage details
     */
    public function problemAction()
    {
        $p = $this->params;

        $host        = $p->shiftRequired('host');
        $service     = $p->shift('service');
        $tplName     = $p->shift('template');
        $ackAuthor   = $p->shift('ack-author', 'Jira');
        $ackPipe     = $p->shift('command-pipe');
        $status      = $p->shiftRequired('state');
        $description = $p->shiftRequired('description');
        $duedate     = $p->shift('due-date');
        $project     = $p->shiftRequired('project');
        $mm = $this->app->getModuleManager();
        if ($p->shift('icingadb') || ! $mm->hasEnabled('monitoring')) {
            if (! $mm->hasEnabled('icingadb')) {
                Logger::error('Icingadb module is not enabled');

                return;
            }

            $backend = new IcingadbBackend();
        } else {
            $backend = new IdoBackend();
        }

        $info = $backend->getMonitoringInfo($host, $service);

        if (! $info->hasObject()) {
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

        $jira = $this->jira();
        $issue = $jira->eventuallyGetLatestOpenIssueFor($project, $host, $service);

        $config = Config::module('jira');

        if ($issue === null) {
            if (\in_array($status, ['UP', 'OK'])) {
                // No existing issue, no problem, nothing to do
                return;
            }
            $params = [
                'project'     => $project,
                'issuetype'   => $p->shiftRequired('issuetype'),
                'summary'     => $p->shiftRequired('summary'),
                'description' => $description,
                'state'       => $status,
                'host'        => $host,
                'service'     => $service,
                'duedate'     => $duedate
            ] + $p->getParams();

            $template = new IssueTemplate();
            if ($tplName) {
                $template->addByTemplateName($tplName);
            }

            $info->setNotificationType('PROBLEM'); // TODO: Once passed, we could deal with RECOVERY
            $template->setMonitoringInfo($info);
            if (! empty($duedate)) {
                $template->addFields(['duedate' => $duedate]);
            }

            $key = $jira->createIssue($template->getFilled($params));

            $ackMessage = "Jira issue $key has been created";
        } else {
            $key = $issue->key;
            $icingaStatus = $config->get('key_fields', 'icingaStatus', 'icingaStatus');
            $currentStatus = isset($issue->fields->$icingaStatus) ? $issue->fields->$icingaStatus : null;
            $ackMessage = "Existing Jira issue $key has been found";
            if ($currentStatus !== $status) {
                $update = new IssueUpdate($jira, $key);
                $update->setCustomField($icingaStatus, $status);
                $update->addComment("Status changed to $status\n" . $description);
                $jira->updateIssue($update);
            }
        }

        if ($this->params->shift('no-acknowledge')) {
            return;
        }

        try {
            if ($ackPipe) {
                $cmd = new LegacyCommandPipe($ackPipe);
            } else {
                $cmd = (new IcingaCommandPipe())->setMonitoringInfo($info);
            }
            if ($cmd->acknowledge($ackAuthor, $ackMessage, $host, $service)) {
                Logger::info("Problem has been acknowledged for $key");
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }
    }
}
