<?php

namespace Icinga\Module\Jira;

use Icinga\Application\Benchmark;
use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;

class RestApi
{
    protected $baseUrl;

    protected $username;

    protected $password;

    protected $curl;
    
    protected $apiName = 'api';
    
    protected $apiVersion = '2';

    protected $enumCustomFields;

    public function __construct($baseUrl, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->baseUrl = rtrim($baseUrl, '/') . '/rest';
    }

    public static function fromConfig()
    {
        $config = Config::module('jira');
        $host = $config->get('api', 'host');
        $url = sprintf(
            'https://%s:%d/%s',
            $config->get('api', 'host'),
            $config->get('api', 'port', 443),
            trim($config->get('api', 'path', ''), '/')
        );

        $user = $config->get('api', 'username');
        $pass = $config->get('api', 'password');

        return new static($url, $user, $pass);
    }

    public function fetchIssue($key)
    {
        return $this->translateCustomFieldNames(
            $this->get("issue/" . urlencode($key))->getResult()
        );
    }

    public function hasIssue($key)
    {
        try {
            $this->fetchIssue($key);
            
            return true;
        } catch (NotFoundError $e) {
            return false;
        }
    }

    public function hasOpenIssueFor($host, $service = null)
    {
    }

    public function createIssue($fields)
    {
        $payload = (object) [
            'fields' => $fields
        ];

        $this->translateNamesToCustomFields($payload);
        $result = $this->post('issue', $payload)->getResult();

        if (property_exists($result, 'key')) {
            return $result->key;
        } else {
            throw new IcingaException(
                'Failed to create a new issue: %s',
                print_r($result, 1)
            );
        }
    }

    public function enumCustomFields()
    {
        if ($this->enumCustomFields === null) {
            $result = [];
            foreach ($this->get('field')->getResult() as $field) {
                if ($field->custom) {
                    $result[$field->id] = $field->name;
                }
            }
            natcasesort($result);

            $this->enumCustomFields = $result;
        }
        
        return $this->enumCustomFields;
    }

    public function translateCustomFieldNames($issue)
    {
        $fields = (object) [];
        $map = $this->enumCustomFields();
        foreach ($issue->fields as $key => $value) {
            if (array_key_exists($key, $map)) {
                $fields->{$map[$key]} = $value;
            } else {
                $fields->$key = $value;
            }
        }

        $issue->fields = $fields;

        return $issue;
    }

    public function translateNamesToCustomFields($issue)
    {
        $fields = (object) [];
        $map = array_flip($this->enumCustomFields());
        foreach ($issue->fields as $key => $value) {
            if (array_key_exists($key, $map)) {
                $fields->{$map[$key]} = $value;
            } else {
                $fields->$key = $value;
            }
        }
        
        $issue->fields = $fields;

        return $issue;
    }

    protected function url($url)
    {
        return implode('/', [$this->baseUrl, $this->apiName, $this->apiVersion, $url]);
    }
    
    protected function request($method, $url, $body = null)
    {
        $auth = sprintf('%s:%s', $this->username, $this->password);
        $headers = array(
            'User-Agent: IcingaWeb2-Jira/v0.0.1',
            // 'Connection: close'
        );

        $headers[] = 'Accept: application/json';

        if ($body !== null) {
            $body = json_encode($body);
            $headers[] = 'Content-Type: application/json';
        }

        $curl = $this->curl();
        $opts = array(
            CURLOPT_URL            => $this->url($url),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $auth,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,

            // TODO: Fix this!
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $opts);
        // TODO: request headers, validate status code

        Benchmark::measure('Rest Api, sending ' . $url);
        $res = curl_exec($curl);
        if ($res === false) {
            throw new IcingaException('CURL ERROR: %s', curl_error($curl));
        }

        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($statusCode === 401) {
            throw new ConfigurationError(
                'Unable to authenticate, please check your API credentials'
            );
        }
        
        if ($statusCode === 404) {
            throw new NotFoundError(
                'Not Found'
            );
        }

        if ($statusCode >= 400) {
            throw new IcingaException(
                'REST API Request failed, got %s',
                $this->getHttpErrorMessage($statusCode)
            );
        }

        Benchmark::measure('Rest Api, got response');

        return RestApiResponse::fromJsonResult($res);
    }

    public function get($url, $body = null)
    {
        return $this->request('get', $url, $body);
    }

    public function getRaw($url, $body = null)
    {
        return $this->request('get', $url, $body, true);
    }

    public function post($url, $body = null)
    {
        return $this->request('post', $url, $body);
    }

    public function put($url, $body = null)
    {
        return $this->request('put', $url, $body);
    }

    public function delete($url, $body = null)
    {
        return $this->request('delete', $url, $body);
    }

    protected function getHttpErrorMessage($statusCode)
    {
        $errors = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            420 => 'Policy Not Fulfilled',
            
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
        ];
        
        if (array_key_exists($statusCode, $errors)) {
            return sprintf('%d: %s', $statusCode, $errors[$statusCode]);
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return sprintf('%d: Unknown 4xx Client Error', $statusCode);
        } else {
            return sprintf('%d: Unknown HTTP Server Error', $statusCode);
        }
    }

    /**
     * @throws Exception
     *
     * @return resource
     */
    protected function curl()
    {
        if ($this->curl === null) {
            $this->curl = curl_init($this->baseUrl);
            if (! $this->curl) {
                throw new Exception('CURL INIT ERROR: ' . curl_error($this->curl));
            }
        }

        return $this->curl;
    }
}
