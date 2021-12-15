<?php

namespace Icinga\Module\Jira\Web\Table;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Jira\Web\RenderingHelper;
use ipl\Html\Html;
use ipl\Html\Table;

class IssuesTable extends Table
{
    protected $issues;

    protected $defaultAttributes = [
        'class' => 'common-table table-row-selectable issue-table',
        'data-base-target' => '_next',
    ];

    public function __construct($issues)
    {
        $this->issues = $issues;
    }

    protected function assemble()
    {
        $helper = new RenderingHelper();

        $this->add(Table::row([
            'Issue',
            'Summary',
            'Created',
        ], null, 'th'));

        foreach ($this->issues as $issue) {
            $this->add(static::tr([
                static::td([
                    $helper->renderAvatar($issue->fields->project),
                    $helper->renderIcon($issue->fields->issuetype),
                    $helper->renderStatusBadge($issue->fields->status),
                ])->setSeparator(' '),
                static::td([
                    Html::tag('strong')->add(
                        Link::create($issue->key, 'jira/issue/show', ['key' => $issue->key])
                    ),
                    $helper->anonymize($issue->fields->summary),
                    Html::tag(
                        'span',
                        ['class' => 'small', 'style' => 'display: block'],
                        $helper->anonymize($issue->fields->description)
                    ),
                ])->setSeparator(' '),
                static::td($helper->shortTimeSince($issue->fields->created)),
            ]));
        }
    }
}
