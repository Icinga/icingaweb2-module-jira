<?php

namespace Icinga\Module\Jira;

use JsonSerializable;
use RuntimeException as RuntimeExceptionAlias;

class IssueUpdate implements JsonSerializable
{
    /** @var RestApi */
    protected $api;

    protected $comments = [];

    protected $fields = [];

    public function __construct(RestApi $api)
    {
        $this->api = $api;
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

    /**
     * @return string
     * @throws \Icinga\Exception\NotFoundError
     */
    public function jsonSerialize()
    {
        $data = (object) [];
        if (! empty($this->comments)) {
            $data->comments = [];
            foreach ($this->comments as $body) {
                $data->comments[] = (object) ['add' => (object) ['body' => $body]];
            }
        }
        if (! empty($this->fields)) {
            $data->fields = [];
            foreach ($this->fields as $name => $value) {
                $data->fields->$name = $value;
            }
        }

        if (empty($data)) {
            throw new RuntimeExceptionAlias('Cannot send empty update');
        }
        $this->api->translateNamesToCustomFields($data);

        return \json_encode($data);
    }
}
