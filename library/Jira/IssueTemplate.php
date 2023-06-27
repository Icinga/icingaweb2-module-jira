<?php

namespace Icinga\Module\Jira;

use Exception;
use Icinga\Application\Config;
use Icinga\Exception\NotFoundError;
use ipl\I18n\Translation;

class IssueTemplate
{
    use Translation;

    protected $custom = [];

    /** @var MonitoringInfo */
    protected $monitoringInfo;

    public function getFilled($params)
    {
        $fields = [];
        foreach ($this->getUnfilledFields() as $key => $tpl) {
            $this->addToFields($fields, $key, $this->fillTemplate($key, $tpl, $params));
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

    /**
     * Fill the fields in the issue template
     *
     * @param $key string Field Name or Field ID
     * @param $string string Field value or string to represent icingaKey, host/service group or custom variables
     * @param $params array Parameters used to create the issue
     *
     * @return array|false|float|int|mixed|string value of the field
     *
     * @throws NotFoundError
     * @throws Exception
     */
    protected function fillTemplate($key, $string, $params)
    {
        $pattern = '/\${([^}\s]+)}/';

        $jira = RestApi::fromConfig();
        if (in_array($key, $jira->enumCustomFields())) {
            $fieldId = array_search($key, $jira->enumCustomFields());
        } else {
            $fieldId = $key;
        }

        $fieldType = $jira->getFieldType($fieldId);

        if (preg_match($pattern, $string, $match)) {
            $name = $match[1];
            $value = null;

            if ($name === 'icingaKey') {
                return $this->getIcingaKeyFromParams($params);
            }
            if (array_key_exists($name, $params)) {
                return $params[$name];
            }

            if (preg_match('/^(?:host|service)\./', $name)) {
                if ($this->monitoringInfo) {
                    $value = $this->monitoringInfo->getProperty($name);

                    $jira = RestApi::fromConfig();
                    if (in_array($key, $jira->enumCustomFields())) {
                        $fieldId = array_search($key, $jira->enumCustomFields());
                    } else {
                        $fieldId = $key;
                    }

                    $fieldType = $jira->getFieldType($fieldId);

                    if (is_object($value)) {
                        throw new Exception(sprintf(
                            $this->translate('Sending objects to custom fields is not supported'),
                            $key
                        ));
                    } elseif (is_array($value) && $fieldType !== 'array') {
                        throw new Exception(sprintf(
                            $this->translate('Custom field %s expects %s, but array given.'),
                            $key,
                            $fieldType
                        ));
                    } elseif (! is_array($value) && $fieldType === 'array') {
                        if ($value === null) {
                            return [];
                        }

                        throw new Exception(sprintf(
                            $this->translate('Custom field %s expects array, but %s given'),
                            $key,
                            gettype($value)
                        ));
                    } elseif ($fieldType === 'number') {
                        $value = filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : (float) $value;
                    }
                }
            }

            if ($name === 'hostgroup') {
                $value = $this->monitoringInfo->fetchHostGroups();
            }

            if ($name === 'servicegroup') {
                $value = $this->monitoringInfo->fetchServiceGroups();
            }

            if ($value === null) {
                return '${' . $name . '}';
            } else {
                return $value;
            }
        }

        if ($fieldType === 'date') {
            return date('Y-m-d', strtotime($string));
        }

        if ($fieldType === 'number') {
            return filter_var($string, FILTER_VALIDATE_INT) !== false ? (int) $string : (float) $string;
        }

        return $string;
    }

    protected function getIcingaKeyFromParams($params)
    {
        if (! isset($params['host'])) {
            throw new \InvalidArgumentException('There is no "host" in $params');
        }
        $host = $params['host'];
        if (array_key_exists('service', $params) && $params['service']) {
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
        $key = $config->get('key_fields', 'icingaKey', 'icingaKey');
        $status = $config->get('key_fields', 'icingaStatus', 'icingaStatus');
        return [
            'project.key'    => '${project}',
            'issuetype.name' => '${issuetype}',
            'summary'        => '${summary}',
            'description'    => '${description}',
            $key             => '${icingaKey}',
            $status          => '${state}',
        ];
    }
}
