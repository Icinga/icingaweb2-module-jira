<?php

namespace Icinga\Module\Jira;

use RuntimeException as RuntimeExceptionAlias;

class IssueUpdate
{
    /** @var RestApi */
    protected $api;

    protected $comments = [];

    protected $fields = [];

    /** @var string */
    protected $key;

    public function __construct(RestApi $api, $issueKey)
    {
        $this->api = $api;
        $this->key = $issueKey;
    }

    public function setCustomField($key, $value)
    {
        $this->fields[$key] = $value;

        return $this;
    }

    public function addComment($body)
    {
        $this->comments[] = $body;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function toObject()
    {
        if (empty($this->comments) && empty($this->fields)) {
            throw new RuntimeExceptionAlias('Cannot send empty update');
        }

        $data = (object) [];
        if (! empty($this->comments)) {
            $data->update = (object) ['comment' => []];
            foreach ($this->comments as $body) {
                $data->update->comment[] = (object) ['add' => (object) ['body' => $body]];
            }
        }
        if (! empty($this->fields)) {
            $data->fields = (object) [];
            foreach ($this->fields as $name => $value) {
                $data->fields->$name = $value;
            }
        }

        $this->api->translateNamesToCustomFields($data);

        return $data;
    }
}
