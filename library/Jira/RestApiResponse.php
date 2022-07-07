<?php

namespace Icinga\Module\Jira;

use RuntimeException;

class RestApiResponse
{
    protected $errorMessage;

    protected $result;

    protected function __construct()
    {
    }

    public static function fromJsonResult($json)
    {
        $response = new static;
        return $response->parseJsonResult($json);
    }

    public static function fromErrorMessage($error)
    {
        $response = new static;
        $response->errorMessage = $error;
        return $response;
    }

    /**
     * @return \stdClass
     */
    public function getResult()
    {
        return $this->result;
    }

    protected function isErrorCode($code)
    {
        $code = (int) ceil($code);
        return $code >= 400;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function succeeded()
    {
        return $this->errorMessage === null;
    }

    protected function parseJsonResult($json)
    {
        if (! $json) {
            $this->result = null;

            return $this;
        }
        $result = @json_decode($json);
        if ($result === null) {
            $this->setJsonError();
            throw new RuntimeException('Parsing JSON result failed: ' . $this->errorMessage);
        }
        $this->result = $result;

        return $this;
    }

    // TODO: just return json_last_error_msg() for PHP >= 5.5.0
    protected function setJsonError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                $this->errorMessage = 'The maximum stack depth has been exceeded';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $this->errorMessage = 'Control character error, possibly incorrectly encoded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $this->errorMessage = 'Invalid or malformed JSON';
                break;
            case JSON_ERROR_SYNTAX:
                $this->errorMessage = 'Syntax error';
                break;
            case JSON_ERROR_UTF8:
                $this->errorMessage = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $this->errorMessage = 'An error occured when parsing a JSON string';
        }

        return $this;
    }
}
