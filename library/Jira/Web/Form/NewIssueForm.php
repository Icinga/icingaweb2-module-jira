<?php

namespace Icinga\Module\Jira\Web\Form;

use Exception;
use Icinga\Application\Logger;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Jira\IcingaCommandPipe;
use Icinga\Module\Jira\IssueTemplate;
use Icinga\Module\Jira\RestApi;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\Service;

class NewIssueForm extends QuickForm
{
    /** @var RestApi */
    private $jira;

    /** @var  MonitoredObject */
    private $object;

    public function setJira(RestApi $jira)
    {
        $this->jira = $jira;

        return $this;
    }

    public function setup()
    {
        $enum = $this->makeEnum(
            $this->jira->get('project')->getResult(),
            'key',
            'name'
        );

        $this->addElement('select', 'project', [
            'label' => $this->translate('JIRA project'),
            'multiOptions' => $this->optionalEnum($enum),
            'class'    => 'autosubmit',
            'required' => true,
        ]);

        $projectName = $this->getSentValue('project');
        if ($projectName === null || ! array_key_exists($projectName, $enum)) {
            return;
        }

        $projects = $this->jira->get(sprintf(
            'issue/createmeta?projectKeys=%s',
            rawurlencode($projectName)
        ))->getResult()->projects;

        $project = current($projects);

        $enum = $this->makeEnum($project->issuetypes, 'name', 'name', function ($type) {
            return $type->subtask;
        });

        $this->addElement('select', 'issuetype', [
            'label' => $this->translate('Issue type'),
            'multiOptions' => $this->optionalEnum($enum),
            'required' => true,
        ]);

        $this->addElement('text', 'summary', [
            'label'       => $this->translate('Summary'),
            'required'    => true,
            'value'       => $this->getObjectDefault('summary'),
            'description' => $this->translate(
                'Summary of this incident'
            ),
        ]);

        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'required'    => true,
            'value'       => $this->getObjectDefault('description'),
            'rows'        => 8,
            'description' => $this->translate(
                'Message body of this issue'
            ),
        ));
    }

    private function getObjectDefault($key)
    {
        $defaults = $this->getObjectDefaults();
        if (array_key_exists($key, $defaults)) {
            return $defaults[$key];
        } else {
            return null;
        }
    }

    public function setObject(MonitoredObject $object)
    {
        $this->object = $object;

        return $this;
    }

    private function getObjectDefaults()
    {
        $object = $this->object;
        if ($object->getType() === 'service') {
            $description = $object->service_output;
            $summary = sprintf(
                '%s on %s is %s',
                $object->service_description,
                $object->host_name,
                $this->getStateName()
            );
        } else {
            $description = $object->host_output;
            $summary = sprintf(
                '%s is %s',
                $object->host_name,
                $this->getStateName()
            );
        }

        $defaults = [
            'summary'     => $summary,
            'description' => $description,
        ];

        return $defaults;
    }

    protected function getStateName()
    {
        $object = $this->object;
        if ($object->getType() === 'service') {
            return strtoupper(Service::getStateText($object->service_state));
        } else {
            return strtoupper(Host::getStateText($object->host_state));
        }
    }

    public function onSuccess()
    {
        $this->createIssue();
        $this->setSuccessMessage('A new incident has been created');
        parent::onSuccess();
    }

    private function createIssue()
    {
        $params = $this->getValues();
        $params['state'] = $this->getStateName();
        $object = $this->object;
        $params['host'] = $host = $object->host_name;
        if ($object->getType() === 'service') {
            $params['service'] = $service = $object->service_description;
        } else {
            $service = null;
        }

        $template = new IssueTemplate();
        // TODO: Should we allow to choose this?
        $template->addByTemplateName('default');
        $key = $this->jira->createIssue($template->getFilled($params));
        $ackMessage = "JIRA issue $key has been created";

        try {
            $cmd = new IcingaCommandPipe();
            if ($cmd->acknowledge('JIRA', $ackMessage, $host, $service)) {
                Logger::info("Problem has been acknowledged for $key");
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }
    }

    protected function makeEnum($data, $key, $name, $reject = null)
    {
        $enum = [];
        foreach ($data as $entry) {
            if (is_callable($reject) && $reject($entry)) {
                continue;
            }
            $value = $this->getProperty($entry, $key);
            $caption = $this->getProperty($entry, $name, $value);
            $enum[$value] = $caption;
        }

        return $enum;
    }

    protected function getProperty($entry, $name, $default = null)
    {
        if (property_exists($entry, $name)) {
            $value = $entry->$name;
        }

        if (empty($value)) {
            return $default;
        } else {
            return $value;
        }
    }
}

