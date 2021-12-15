<?php

namespace Icinga\Module\Jira\Clicommands;

use Exception;
use Icinga\Application\Logger;
use Icinga\Application\Config;
use Icinga\Module\Jira\IcingaCommandPipe;
use Icinga\Module\Jira\Cli\Command;
use Icinga\Module\Jira\IssueTemplate;
use Icinga\Module\Jira\IssueUpdate;
use Icinga\Module\Jira\LegacyCommandPipe;
use Icinga\Module\Jira\MonitoringInfo;

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
     *   --project <project-name>     JIRA project name, like "ITSM"
     *   --issuetype <type-name>      JIRA issue type, like "Incident"
     *   --summary <summary>          JIRA issue summary
     *   --description <description>  JIRA issue description text
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
     *                              defaults to "JIRA"
     *   --no-acknowledge           Do not acknowledge Icinga problem
     *   --command-pipe <path>      Legacy command pipe, allows to run without
     *                              depending on a configured monitoring module
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
        $ackAuthor   = $p->shift('ack-author', 'JIRA');
        $ackPipe     = $p->shift('command-pipe');
        $status      = $p->shiftRequired('state');
        $description = $p->shiftRequired('description');
        $duedate     = $p->shift('due-date');

        $jira = $this->jira();
        $issue = $jira->eventuallyGetLatestOpenIssueFor($host, $service);

        $config = Config::module('jira');

        if ($issue === null) {
            if (\in_array($status, ['UP', 'OK'])) {
                // No existing issue, no problem, nothing to do
                return;
            }
            $params = [
                'project'     => $p->shiftRequired('project'),
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
            $info = new MonitoringInfo($host, $service);
            $info->setNotificationType('PROBLEM'); // TODO: Once passed, we could deal with RECOVERY
            $template->setMonitoringInfo($info);
            if (! empty($duedate)) {
                $template->addFields(['duedate' => $duedate]);
            }

            $key = $jira->createIssue($template->getFilled($params));

            $ackMessage = "JIRA issue $key has been created";
        } else {
            $key = $issue->key;
            $icingaStatus = $config->get('ui', 'field_icingaStatus', 'customfield_19220');
            $currentStatus = isset($issue->fields->$icingaStatus) ? $issue->fields->$icingaStatus : null;
            $ackMessage = "Existing JIRA issue $key has been found";
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
                $cmd = new IcingaCommandPipe();
            }
            if ($cmd->acknowledge($ackAuthor, $ackMessage, $host, $service)) {
                Logger::info("Problem has been acknowledged for $key");
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }
    }
}
