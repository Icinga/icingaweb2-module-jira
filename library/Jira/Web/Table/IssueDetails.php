<?php

namespace Icinga\Module\Jira\Web\Table;

use dipl\Html\Html;
use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\NameValueTable;
use Icinga\Module\Jira\Web\RenderingHelper;

class IssueDetails extends NameValueTable
{
    use TranslationHelper;

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

        $fields = $issue->fields;
        $projectKey = $fields->project->key;

        $icingaKey = preg_replace_callback('/^BEGIN(.+)END$/', '$1', $fields->icingaKey);
        $parts = explode('!', $icingaKey);
        $host = array_shift($parts);
        if (empty($parts)) {
            $service = null;
        } else {
            $service = array($parts);
        }
        $user = $fields->icingaUser;

        $this->addNameValuePairs([
            $this->translate('Issue') => $helper->linkToJira(
                $key,
                ['browse', $key],
                ['class'  => 'icon-right-big']
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
        $this->body()->add(static::tr(static::td($content, ['colspan' => 2])));

        return $this;
    }
    
    protected function addComments($comments)
    {
        foreach ($comments as $comment) {
            if (property_exists($comment, 'author')) {
                $this->addComment(
                    $comment->author->displayName,
                    $comment->created,
                    $comment->body
                );
            }
        }
        
        return $this;
    }

    protected function addComment($author, $time, $body)
    {
        return $this->addWideRow([
            Html::tag('h3', null, Html::sprintf(
                '%s: %s',
                $this->helper->shortTimeSince($time),
                $author
            )),
            Html::tag('pre', null, $body),
        ]);
    }
}
