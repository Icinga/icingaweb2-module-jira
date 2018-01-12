<?php

namespace Icinga\Module\Jira;

use Icinga\Application\Config;

class IssueTemplate
{
    protected $custom = [];

    public function getFilled($params)
    {
        $fields = [];
        foreach ($this->getUnfilledFields() as $key => $tpl) {
            $this->addToFields($fields, $key, $this->fillTemplate($tpl, $params));
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

    protected function addToFields(& $fields, $key, $value)
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
        $pattern = '/\$\{([a-zA-Z0-9]+)\}/';
        return preg_replace_callback(
            $pattern,
            function ($match) use ($params) {
                $name = $match[1];
                if ($name === 'icingaKey') {
                    return $this->getIcingaKeyFromParams($params);
                }
                if (array_key_exists($name, $params)) {
                    return $params[$name];
                } else {
                    return '${' . $name . '}';
                }
            },
            $string
        );
    }

    protected function getIcingaKeyFromParams($params)
    {
        if (array_key_exists('service', $params) && strlen($params['service'])) {
            return sprintf('%s!%s', $params['host'], $params['service']);
        } else {
            return $params['host'];
        }
    }

    protected function getCustomFields()
    {
        return $this->custom;
    }

    protected function getDefaultFields()
    {
        return [
            'project.key'    => '${project}',
            'issuetype.name' => '${issueType}',
            'summary'        => '${summary}',
            'description'    => '${description}',
            'icingaKey'      => '${icingaKey}',
            'icingaStatus'   => '${status}',
        ];
    }
}
