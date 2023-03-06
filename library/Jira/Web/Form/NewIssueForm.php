<?php

namespace Icinga\Module\Jira\Web\Form;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Module\Jira\IcingaCommandPipe;
use Icinga\Module\Jira\IssueTemplate;
use Icinga\Module\Jira\MonitoringInfo;
use Icinga\Module\Jira\RestApi;
use Icinga\Module\Jira\Web\Form;

class NewIssueForm extends Form
{
    /** @var RestApi */
    private $jira;

    /** @var MonitoringInfo */
    private $monitoringInfo;

    /** @var Config */
    private $config;

    public function __construct(RestApi $jira, Config $config, MonitoringInfo $info)
    {
        $this->jira = $jira;
        $this->config = $config;
        $this->monitoringInfo = $info;
    }

    protected function assemble()
    {
        $this-> prepareWebForm();
        $this->addAttributes([
            'class' => 'icinga-form icinga-controls'
        ]);
        $config = $this->config;
        $defaultProject = $config->get('ui', 'default_project');
        $defaultTemplate = $config->get('ui', 'default_template');
        $defaultAck = $config->get('ui', 'acknowledge', 'y');

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

        $deployment = $this->config->getSection('deployment');

        //Createmeta for the jira server above v9.x.x has been updated
        // check https://docs.atlassian.com/software/jira/docs/api/REST/9.0.0/#project-getProject
        if (
            ($this->jira->isServer() && version_compare($this->jira->getJiraVersion(), '9', '>='))
            || (
                $deployment->get('type') === 'cloud'
                && ! (int) $deployment->get('legacy')
            )
        ) {
            $project = $this->jira->get(sprintf(
                'issue/createmeta/%s/issuetypes/',
                rawurlencode($projectName)
            ))->getResult();

            $data = $project->values;
        } else {
            $projects = $this->jira->get(sprintf(
                'issue/createmeta?projectKeys=%s',
                rawurlencode($projectName)
            ))->getResult()->projects;

            $project = current($projects);
            $data = $project->issuetypes;
        }

        $enum = $this->makeEnum($data, 'name', 'name', function ($type) {
            return $type->subtask;
        });

        asort($enum, SORT_FLAG_CASE | SORT_NATURAL);

        $this->addElement('select', 'issuetype', [
            'label' => $this->translate('Issue type'),
            'multiOptions' => $this->optionalEnum($enum),
            'value'        => $config->get('ui', 'default_issuetype'),
            'required'     => true,
        ]);

        $this->addElement('text', 'summary', [
            'label'       => $this->translate('Summary'),
            'required'    => true,
            'value'       => $this->monitoringInfo->getDefaultSummary(),
            'description' => $this->translate(
                'Summary of this incident'
            ),
        ]);
        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'required'    => true,
            'value'       => $this->monitoringInfo->getOutput(),
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
        $this->addElement('select', 'acknowledge', [
            'label'       => $this->translate('Acknowledge'),
            'options' => [
                'y'  => $this->translate('Yes'),
                'n'  => $this->translate('No'),
            ],
            'value' =>  $defaultAck,
            'description' => $this->translate(
                'Whether the Icinga problem should be acknowledged. The newly'
                . ' created JIRA issue will be linked in the related comment.'
            )
        ]);

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Create Issue')
        ]);
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

    public function onSuccess()
    {
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function createIssue()
    {
        $info = $this->monitoringInfo;
        $params = [
            'state'   => $info->getStateName(),
            'host'    => $host = $info->getHostname(),
            'service' => $service = $info->getService(),
        ] + $this->getValues();

        $template = new IssueTemplate();
        $template->addByTemplateName($this->getValue('template'));
        $template->setMonitoringInfo($this->monitoringInfo);
        $key = $this->jira->createIssue($template->getFilled($params));
        if ($this->getValue('acknowledge') === 'n') {
            return;
        }
        $this->eventuallyAcknowledge($key, $host, $service);
    }

    protected function eventuallyAcknowledge($key, $host, $service)
    {
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
