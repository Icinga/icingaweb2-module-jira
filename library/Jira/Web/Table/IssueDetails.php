<?php

namespace Icinga\Module\Jira\Web\Table;

use Icinga\Application\Config;
use Icinga\Module\Jira\Web\RenderingHelper;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use ipl\Html\Table;
use ipl\I18n\Translation;
use ipl\Web\Widget\Icon;

class IssueDetails extends Table
{
    use Translation;

    protected $defaultAttributes = ['class' => 'name-value-table'];

    protected $helper;

    protected $issue;

    public function __construct($issue)
    {
        $this->helper = new RenderingHelper();
        $this->issue = $issue;
    }

    protected function assemble()
    {
        $helper = $this->helper;
        $issue = $this->issue;
        $key = $issue->key;
        $config = Config::module('jira');

        $fields = $issue->fields;
        $projectKey = $fields->project->key;
        $keyField = $config->get('jira_key_fields', 'field_icingaKey', 'icingaKey');

        $icingaKey = preg_replace('/^BEGIN(.+)END$/', '$1', $fields->$keyField);
        $parts = explode('!', $icingaKey);
        $host = array_shift($parts);
        if (empty($parts)) {
            $service = null;
        } else {
            $service = array_shift($parts);
        }
        if (isset($fields->icingaUser)) {
            $user = $fields->icingaUser;
        } else {
            $user = null;
        }

        $this->addNameValuePairs([
            $this->translate('Issue') => $helper->linkToJira(
                [new Icon('arrow-right'), $key],
                ['browse', $key]
            ),
            $this->translate('Project') => $helper->linkToJira(
                [$helper->renderAvatar($fields->project), $projectKey],
                ['browse', $projectKey]
            ),
            $this->translate('Issue Type') => [
                $helper->renderIcon($fields->issuetype),
                ' ',
                sprintf(' %s: %s', $fields->issuetype->name, $fields->issuetype->description)
            ],
            $this->translate('Status') => $helper->renderStatusBadge($fields->status),
            $this->translate('Priority') => [
                $helper->renderIcon($fields->priority),
                ' ' . $fields->priority->name
            ],
            $this->translate('Created') => $helper->shortTimeSince($fields->created, false),
        ]);

        if ($host !== null) {
            $this->addNameValueRow(
                $this->translate('Host'),
                $helper->linkToMonitoringHost($host)
            );
        }
        if ($service !== null) {
            $this->addNameValueRow(
                $this->translate('Service'),
                $helper->linkToMonitoringService($host, $service)
            );
        }
        if ($user !== null) {
            $this->addNameValueRow(
                $this->translate('Created by'),
                $user
            );
        }

        $this->addComments(array_reverse($fields->comment->comments));
        $this->addComment(
            $helper->anonymize($fields->summary),
            $fields->created,
            $helper->anonymize($fields->description)
        );
    }

    protected function addWideRow($content)
    {
        $this->add(static::tr(static::td($content, ['colspan' => 2])));

        return $this;
    }

    protected function addComments($comments)
    {
        foreach ($comments as $comment) {
            if (property_exists($comment, 'author')) {
                $this->addComment(
                    $this->formatAuthor($comment->author),
                    $comment->created,
                    $comment->body
                );
            }
        }

        return $this;
    }

    protected function formatAuthor($author)
    {
        $size = 48;
        $key = "${size}x${size}";
        if (isset($author->avatarUrls->$key)) {
            return [
                // TODO: move styling to CSS
                Html::tag('img', [
                    'src' => $author->avatarUrls->$key,
                    'alt' => '',
                    'width' => $size,
                    'height' => $size,
                    'align' => 'left',
                    'style' => 'margin-right: 1em; border-radius: 50%;',
                ]),
                ' ',
                $author->displayName
            ];
        } else {
            return $author->displayName;
        }
    }

    protected function formatBody($body)
    {
        $html = Html::wantHtml($body)->render();

        // This is safe.
        return new HtmlString($this->replaceLinks($html));
    }

    protected function replaceLinks($string)
    {
        return \preg_replace_callback('/\[([^|]+)\|([^]]+)]/', function ($match) {
            return Html::tag('a', ['href' => $match[2], 'target' => '_blank'], $match[1]);
        }, $string);
    }

    protected function addComment($author, $time, $body)
    {
        return $this->addWideRow([
            Html::tag('h3', null, Html::sprintf(
                '%s: %s',
                $this->helper->shortTimeSince($time),
                $author
            )),
            Html::tag(
                'pre',
                ['style' => 'background-color: transparent'],
                $this->formatBody($body)
            ),
        ]);
    }

    protected function createNameValueRow($name, $value)
    {
        return $this::tr([$this::th($name), $this::td($value)]);
    }

    protected function addNameValueRow($name, $value)
    {
        return $this->add($this->createNameValueRow($name, $value));
    }

    protected function addNameValuePairs($pairs)
    {
        foreach ($pairs as $name => $value) {
            $this->addNameValueRow($name, $value);
        }

        return $this;
    }
}
