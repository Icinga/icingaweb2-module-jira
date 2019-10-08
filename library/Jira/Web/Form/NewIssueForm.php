<?php

namespace Icinga\Module\Jira\Web\Form;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Jira\IcingaCommandPipe;
use Icinga\Module\Jira\IssueTemplate;
use Icinga\Module\Jira\MonitoringInfo;
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

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $config = Config::module('jira');
        $defaultProject = $config->get('ui', 'default_project');
        $defaultTemplate = $config->get('ui', 'default_template');
        $defaultAck = $config->get('ui', 'acknowledge');

        $enum = $this->makeEnum(
            $this->jira->get('project')->getResult(),
            'key',
            'name'
        );

        $this->addElement('select', 'project', [
            'label' => $this->translate('JIRA project'),
            'multiOptions' => $this->optionalEnum($enum),
            'value'    => $defaultProject,
            'class'    => 'autosubmit',
            'required' => true,
        ]);

        $projectName = $this->getSentValue('project');
        if ($projectName === null) {
            $projectName = $defaultProject;
        }
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
            'value'        => $config->get('ui', 'default_issuetype'),
            'required'     => true,
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
        $this->addElement('select', 'template', [
            'label' => $this->translate('Template'),
            'multiOptions' => $this->optionalEnum($this->enumTemplates()),
            'value'    => $defaultTemplate,
        ]);
        $this->addBoolean('acknowledge', [
            'label'       => $this->translate('Acknowledge'),
            'description' => $this->translate(
                'Whether the Icinga problem should be acknowledged. The newly'
                . ' created JIRA issue will be linked in the related comment.'
            )
        ], $defaultAck);
    }

    private function enumTemplates()
    {
        $templates = [];
        $templateList = Config::module('jira', 'templates')->keys();
        foreach ($templateList as $template) {
            $templates[$template] = $template;
        }

        return $templates;
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

    /**
     * @param $key
     * @param $options
     * @param null $default
     * @throws \Zend_Form_Exception
     */
    protected function addBoolean($key, $options, $default = null)
    {
        if ($default === null) {
            $this->addElement('OptionalYesNo', $key, $options);
        } else {
            $this->addElement('YesNo', $key, $options);
            $this->getElement($key)->setValue($default);
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
        $description = "Notification Type: MANUAL\n";

        if ($object->getType() === 'service') {
            $description .= sprintf(
                "Service: %s\n",
                $this->jira->linkToIcingaService($object->host_name, $object->service_description)
            ) . sprintf(
                "Host: %s\n",
                $this->jira->linkToIcingaHost($object->host_name)
            );
            $description .= "\n$object->service_output\n"
                . str_replace('\n', "\n", $object->service_long_output);
            $summary = sprintf(
                '%s on %s is %s',
                $object->service_description,
                $object->host_name,
                $this->getStateName()
            );
        } else {
            $description .= sprintf(
                "Host: %s\n",
                $this->jira->linkToIcingaHost($object->host_name)
            );
            $description .= "\n$object->host_output\n"
                . str_replace('\n', "\n", $object->host_long_output);
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

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function onSuccess()
    {
        $this->createIssue();
        $this->setSuccessMessage('A new incident has been created');
        parent::onSuccess();
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
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
        $template->addByTemplateName($this->getValue('template'));
        $template->setMonitoringInfo(new MonitoringInfo($host, $service));
        $key = $this->jira->createIssue($template->getFilled($params));
        if ($this->getValue('acknowledge') === 'n') {
            return;
        }
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

