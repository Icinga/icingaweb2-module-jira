<?php

namespace Icinga\Module\Jira;

use Icinga\Application\Config;

class IssueTemplate
{
    protected $custom = [];

    /** @var MonitoringInfo */
    protected $monitoringInfo;

    public function getFilled($params)
    {
        $fields = [];
        foreach ($this->getUnfilledFields() as $key => $tpl) {
            if ($key === 'duedate') {
                $fields['duedate'] = date('Y-m-d', strtotime($tpl));
            } else {
                $this->addToFields($fields, $key, $this->fillTemplate($tpl, $params));
            }
        }

        if (isset($fields['description'])) {
            $fields['description'] = $this->monitoringInfo->getDescriptionHeader() . "\n" . $fields['description'];
        }

        return $fields;
    }

    protected function getUnfilledFields()
    {
        return $this->getCustomFields() + $this->getDefaultFields();
    }

    /**
     * @param mixed $fields array, Config section...
     */
    public function addFields($fields)
    {
        foreach ($fields as $key => $value) {
            $this->custom[$key] = $value;
        }
    }

    public function addByTemplateName($name)
    {
        $this->addFields(Config::module('jira', 'templates')->getSection($name));

        return $this;
    }

    public function setMonitoringInfo(MonitoringInfo $info)
    {
        $this->monitoringInfo = $info;

        return $this;
    }

    protected function addToFields(&$fields, $key, $value)
    {
        $dot = strpos($key, '.');
        if (false === $dot) {
            $fields[$key] = $value;
        } else {
            $remaining = substr($key, $dot + 1);
            $key = substr($key, 0, $dot);
            if (! array_key_exists($key, $fields)) {
                $fields[$key] = [];
            }

            $this->addToFields($fields[$key], $remaining, $value);
        }
    }

    protected function fillTemplate($string, $params)
    {
        $pattern = '/\${([^}\s]+)}/';
        return preg_replace_callback(
            $pattern,
            function ($match) use ($params) {
                $name = $match[1];
                if ($name === 'icingaKey') {
                    return $this->getIcingaKeyFromParams($params);
                }
                if (array_key_exists($name, $params)) {
                    return $params[$name];
                }

                $value = null;
                if (preg_match('/^(?:host|service)\./', $name)) {
                    if ($this->monitoringInfo) {
                        $value = $this->monitoringInfo->getProperty($name);
                    }
                }

                if ($value === null) {
                    return '${' . $name . '}';
                } else {
                    return $value;
                }
            },
            $string
        );
    }

    protected function getIcingaKeyFromParams($params)
    {
        if (! isset($params['host'])) {
            throw new \InvalidArgumentException('There is no "host" in $params');
        }
        $host = $params['host'];
        if (array_key_exists('service', $params) && strlen($params['service'])) {
            $service = $params['service'];
        } else {
            $service = null;
        }

        return RestApi::makeIcingaKey($host, $service);
    }

    protected function getCustomFields()
    {
        return $this->custom;
    }

    protected function getDefaultFields()
    {
        $config = Config::module('jira');
        $Key = $config->get('jira_key_fields', 'field_icingaKey', 'icingaKey');
        $Status = $config->get('jira_key_fields', 'field_icingaStatus', 'customfield_19220');
        return [
            'project.key'    => '${project}',
            'issuetype.name' => '${issuetype}',
            'summary'        => '${summary}',
            'description'    => '${description}',
            $Key             => '${icingaKey}',
            $Status          => '${state}',
        ];
    }
}
