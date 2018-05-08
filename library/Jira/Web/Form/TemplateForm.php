<?php

namespace Icinga\Module\Jira\Web\Form;

use dipl\Html\Html;
use dipl\Html\BaseHtmlElement;
use dipl\Translation\TranslationHelper;
use Exception;
use Icinga\Module\Jira\RestApi;

class TemplateForm extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'form';

    protected $jira;

    public function __construct(RestApi $jira)
    {
        $this->jira = $jira;
    }

    protected function assemble()
    {
        try {
            $projects = $this->jira->get('project')->getResult();
        } catch (Exception $e) {
            $this->add(Html::tag('p', ['class' => 'state-hint error'], sprintf(
                $this->translate('Unable to talk to JIRA, please check your configuration: %s'),
                $e->getMessage()
            )));

            return;
        }

        $this->add($this->makeSelect('project', $projects, [
            'value'    => 'key',
            'caption'  => 'name',
            'imagesrc' => function ($project) {
                return $project->avatarUrls->{'16x16'};
            }
        ]));

        $projectName = 'ITSM';
        // $projectName = null;

        if ($projectName === null) {
            return;
        }

        $projects = $this->jira->get(sprintf(
            // 'issue/createmeta?projectKeys=%s&expand=projects.issuetypes.fields',
            'issue/createmeta?projectKeys=%s',
            rawurlencode($projectName)
        ))->getResult()->projects;

        foreach ($projects as $project) {
            $this->add($this->makeSelect('issue_type', $project->issuetypes, [
                'imagesrc' => 'iconUrl',
                'caption'  => 'name',
                'value'    => 'id',
                'reject'   => function ($type) {
                    return $type->subtask;
                }
            ]));
        }

        $this->add($this->createCustomFieldSelect());
    }

    protected function createCustomFieldSelect()
    {
        $select = Html::tag('select', [
            'class' => 'jira-ddslick',
        ]);
        foreach ($this->jira->enumCustomFields() as $id => $name) {
            $select->add(
                Html::tag('option', ['value' => $id], $name)
            );
        }

        return $select;
    }

    protected function makeSelect($name, $data, $propertyMap)
    {
        $select = Html::tag('select', [
            'class' => 'jira-ddslick',
            'id'    => $name,
            'name'  => $name,
        ]);
        
        if (array_key_exists('reject', $propertyMap)) {
            $reject = $propertyMap['reject'];
        } else {
            $reject = null;
        }

        // $select->add(Html::tag('option', ['value' => '', 'data-description' => ''], '- please choose -'));
        foreach ($data as $key => $entry) {
            if (is_callable($reject) && $reject($entry)) {
                continue;
            }

            $value = $this->getProperty(
                $entry,
                $this->getPropertyName('value', $propertyMap),
                ''
            );
            $options = [
                'value' => $value,
                'data-description' => $this->getProperty(
                    $entry,
                    $this->getPropertyName('description', $propertyMap),
                    ' '
                ),
            ];
            $caption = $this->getProperty(
                $entry,
                $this->getPropertyName('caption', $propertyMap),
                $value
            ) .  $this->getProperty(
                    $entry,
                    $this->getPropertyName('description', $propertyMap) . 'NO',
                    ' '
                );

            $image = $this->getProperty($entry, $this->getPropertyName('imagesrc', $propertyMap));
            if ($image !== null) {
                $options['data-imagesrc'] = $image;
            }
            $select->add(Html::tag('option', $options, $caption));
        }

        return $select;
    }

    protected function getProperty($entry, $name, $default = null)
    {
        if (is_callable($name)) {
            $value = $name($entry);
        } elseif (is_object($entry)) {
            if (property_exists($entry, $name)) {
                $value = $entry->$name;
            }
        } elseif (is_array($entry)) {
            if (array_key_exists($name, $entry)) {
                $value = $entry[$name];
            }
        }
        
        if (empty($value)) {
            return $default;
        } else {
            return $value;
        }
    }

    protected function getPropertyName($name, $map)
    {
        if (array_key_exists($name, $map)) {
            return $map[$name];
        } else {
            return $name;
        }
    }
}
