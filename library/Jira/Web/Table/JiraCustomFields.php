<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Web\Table;

use Icinga\Module\Jira\RestApi;
use ipl\Html\Table;
use ipl\I18n\Translation;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class JiraCustomFields extends Table
{
    use Translation;

    protected $defaultAttributes = [
        'class'            => ['common-table', 'table-row-selectable', 'issue-table'],
        'data-base-target' => '_next',
    ];

    /** @var array */
    protected $fields;

    /** @var string */
    protected $templateName;

    /** @var RestApi */
    protected $jira;

    public function __construct(array $fields, string $templateName, RestApi $jira)
    {
        $this->fields = $fields;
        $this->templateName = $templateName;
        $this->jira = $jira;
    }

    protected function assemble()
    {
        if (empty($this->fields)) {
            $this->add($this->translate('No custom fields added'));
        }

        $customFields = $this->jira->enumCustomFields();
        $customFields['duedate'] = 'Due Date';
        foreach ($this->fields as $field => $value) {
            $dot = strpos($field, '.');
            if (
                $dot !== false
                && (! array_key_exists($field, $customFields) || ! in_array($field, $customFields))
            ) {
                continue;
            }

            $this->add(static::tr(
                [
                    static::td(new Link(
                        $customFields[$field] ?? $field,
                        Url::fromPath(
                            'jira/field',
                            [
                                'template' => $this->templateName,
                                'fieldId' => $field
                            ]
                        )
                    )),
                ]
            ));
        }
    }
}
