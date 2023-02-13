<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Web\Table;

use ipl\Html\Table;
use ipl\I18n\Translation;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class TemplateTable extends Table
{
    use Translation;

    /** @var array */
    protected $templates;

    protected $defaultAttributes = [
        'class'            => 'common-table table-row-selectable issue-table',
        'data-base-target' => '_next',
    ];

    public function __construct(array $templates)
    {
        $this->templates = $templates;
    }

    protected function assemble()
    {
        if (empty($this->templates)) {
            $this->add($this->translate('No templates added'));
        }

        foreach ($this->templates as $templateName => $templateFields) {
            $this->add(static::tr([
                static::td(new Link(
                    $templateName,
                    Url::fromPath('jira/templates/edit', ['template' => $templateName])
                )),
            ]));
        }
    }
}
