<?php

namespace Icinga\Module\Jira;

use Exception;
use Icinga\Application\Benchmark;
use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Exception\NotFoundError;
use RuntimeException;

class RestApi
{
    protected $baseUrl;

    protected $icingaUrl;

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
        $this->baseUrl = \rtrim($baseUrl, '/') . '/rest';
    }

    /**
     * @return static
     */
    public static function fromConfig()
    {
        $config = Config::module('jira');
        $host = $config->get('api', 'host');
        $scheme = $config->get('api', 'scheme', 'https');
        if ($host === null) {
            throw new RuntimeException('No JIRA host has been configured');
        }
        $url = \rtrim(\sprintf(
            '%s://%s:%d/%s',
            $scheme,
            $host,
            $config->get('api', 'port', $scheme === 'https' ? 443 : 80),
            \trim($config->get('api', 'path', ''), '/')
        ), '/');

        $user = $config->get('api', 'username');
        $pass = $config->get('api', 'password');

        $api = new static($url, $user, $pass);
        if ($url = $config->get('icingaweb', 'url')) {
            $api->icingaUrl = \rtrim($url, '/');
        }

        return $api;
    }

    /**
     * @param $key
     * @return mixed
     * @throws NotFoundError
     */
    public function fetchIssue($key)
    {
        $issue = $this->get("issue/" . urlencode($key))->getResult();
        Benchmark::measure('A single issue has been fetched');

        return $this->translateCustomFieldNames($issue);
    }

    /**
     * @param $key
     * @return bool
     */
    public function hasIssue($key)
    {
        try {
            $this->fetchIssue($key);
            
            return true;
        } catch (NotFoundError $e) {
            return false;
        }
    }

    public function eventuallyGetLatestOpenIssueFor($host, $service = null)
    {
        try {
            $start = 0;
            $limit = 1;
            $query = $this->prepareIssueQuery($host, $service, true);

            $issues = $this->post('search', [
                'jql'        => $query,
                'startAt'    => $start,
                'maxResults' => $limit,
                'fields'     => [ 'icingaKey' ],
            ])->getResult()->issues;

            if (empty($issues)) {
                Benchmark::measure('Found no (optional) issue');

                return null;
            } else {
                Benchmark::measure('Fetched an (optional) single issues');

                return $this->fetchIssue(current($issues)->key);
            }
        } catch (Exception $e) {
            return null;
        }
    }

    public function updateIssue(IssueUpdate $update)
    {
        return $this->put('issue/' . urlencode($update->getKey()), $update->toObject());
    }

    protected function prepareIssueQuery($host = null, $service = null, $onlyOpen = true)
    {
        // TODO: eventually also filter for project = "..."?
        $query = 'creator = currentUser()';

        if ($onlyOpen) {
            $query .= ' AND resolution is empty';
        }

        if ($host === null) {
            $query .= ' AND icingaKey ~ "BEGIN*"';
        } else {
            $icingaKey = static::makeIcingaKey($host, $service);

            // There is no exact field matcher out of the box on JIRA, this is
            // an ugly work-around. We search for "BEGINhostnameEND" or
            // "BEGINhostname!serviceEND"
            $query .= \sprintf(' AND icingaKey ~ "\"%s\""', $icingaKey);
        }

        $query .= ' ORDER BY created DESC';

        return $query;
    }

    public static function makeIcingaKey($host, $service = null)
    {
        $icingaKey = "BEGIN$host";
        if ($service !== null) {
            $icingaKey .= "!$service";
        }

        return "${icingaKey}END";
    }

    /**
     * @param null $host
     * @param null $service
     * @param bool $onlyOpen
     * @return mixed
     * @throws NotFoundError
     */
    public function fetchIssues($host = null, $service = null, $onlyOpen = true)
    {
        $start = 0;
        $limit = 15;
        $query = $this->prepareIssueQuery($host, $service, $onlyOpen);
        $fields = [
            'project',
            'issuetype',
            'description',
            'summary',
            'status',
            'created',
            'icingaStatus',
            'icingaKey',
        ];

        $issues = $this->post('search', [
            'jql'        => $query,
            'startAt'    => $start,
            'maxResults' => $limit,
            'fields'     => $fields,
        ])->getResult()->issues;

        Benchmark::measure(sprintf('Fetched %s issues', \count($issues)));

        return $issues;
    }

    /**
     * @param $fields
     * @throws NotFoundError
     * @return string
     */
    public function createIssue($fields)
    {
        $payload = (object) [
            'fields' => $fields
        ];

        $this->translateNamesToCustomFields($payload);
        $result = $this->post('issue', $payload)->getResult();

        if (property_exists($result, 'key')) {
            $key = $result->key;
            Logger::info('New JIRA issue %s has been created', $key);
            Benchmark::measure('A new issue has been created');

            return $key;
        } else {
            throw new RuntimeException(
                'Failed to create a new issue: %s',
                \print_r($result, 1)
            );
        }
    }

    /**
     * @return array|null
     * @throws NotFoundError
     */
    public function enumCustomFields()
    {
        if ($this->enumCustomFields === null) {
            Benchmark::measure('Need to fetch custom field mappings');
            $result = [];
            $response = $this->get('field');
            foreach ($response->getResult() as $field) {
                if ($field->custom) {
                    $result[$field->id] = $field->name;
                }
            }
            \natcasesort($result);

            $this->enumCustomFields = $result;
            Benchmark::measure(\sprintf('Got %d custom field mappings', \count($result)));
        }

        return $this->enumCustomFields;
    }

    /**
     * @param $issue
     * @throws NotFoundError
     * @return object
     */
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

    /**
     * @param $issue
     * @throws NotFoundError
     * @return object
     */
    public function translateNamesToCustomFields($issue)
    {
        $fields = (object) [];
        $map = \array_flip($this->enumCustomFields());
        foreach ($issue->fields as $key => $value) {
            if (\array_key_exists($key, $map)) {
                $fields->{$map[$key]} = $value;
            } else {
                $fields->$key = $value;
            }
        }
        
        $issue->fields = $fields;

        return $issue;
    }

    public function linkToIcingaHost($hostname)
    {
        if ($this->icingaUrl === null) {
            return $hostname;
        } else {
            return \sprintf(
                '[%s|%s/monitoring/host/show?host=%s]',
                $hostname,
                $this->icingaUrl,
                \rawurlencode($hostname)
            );
        }
    }

    public function linkToIcingaService($hostname, $service)
    {
        if ($this->icingaUrl === null) {
            return $service;
        } else {
            return \sprintf(
                '[%s|%s/monitoring/service/show?host=%s&service=%s]',
                $service,
                $this->icingaUrl,
                \rawurlencode($hostname),
                \rawurlencode($service)
            );
        }
    }

    public function url($url)
    {
        return \implode('/', [$this->baseUrl, $this->apiName, $this->apiVersion, $url]);
    }

    /**
     * @param $method
     * @param $url
     * @param mixed $body
     * @throws NotFoundError
     * @return RestApiResponse
     */
    protected function request($method, $url, $body = null)
    {
        $auth = \sprintf('%s:%s', $this->username, $this->password);
        $headers = [
            'User-Agent: IcingaWeb2-Jira/v1.0',
        ];

        $headers[] = 'Accept: application/json';

        if ($body !== null) {
            $body = \json_encode($body);
        }
        $headers[] = 'Content-Type: application/json';

        $curl = $this->curl();
        $opts = array(
            CURLOPT_URL            => $this->url($url),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $auth,
            CURLOPT_CUSTOMREQUEST  => \strtoupper($method),
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
        $res = \curl_exec($curl);
        if ($res === false) {
            throw new RuntimeException('CURL ERROR: ' . \curl_error($curl));
        }

        $statusCode = \curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($statusCode === 401) {
            throw new RuntimeException(
                'Unable to authenticate, please check your API credentials'
            );
        }
        
        if ($statusCode === 404) {
            throw new NotFoundError(
                'Not Found'
            );
        }

        if ($statusCode >= 400) {
            $result = @\json_decode($res);
            if ($result && \property_exists($result, 'errorMessages') && ! empty($result->errorMessages)) {
                throw new RuntimeException(\implode('; ', $result->errorMessages));
            }
            if ($result && \property_exists($result, 'errors') && ! empty($result->errors)) {
                throw new RuntimeException(\implode('; ', (array) $result->errors));
            }

            throw new RuntimeException(
                'REST API Request failed, got %s',
                $this->getHttpErrorMessage($statusCode)
            );
        }

        Benchmark::measure('Rest Api, got response');

        return RestApiResponse::fromJsonResult($res);
    }

    /**
     * @param $url
     * @param null $body
     * @return RestApiResponse
     * @throws NotFoundError
     */
    public function get($url, $body = null)
    {
        return $this->request('get', $url, $body);
    }

    /**
     * @param $url
     * @param null $body
     * @return RestApiResponse
     * @throws NotFoundError
     */
    public function post($url, $body = null)
    {
        return $this->request('post', $url, $body);
    }

    /**
     * @param $url
     * @param null $body
     * @return RestApiResponse
     * @throws NotFoundError
     */
    public function put($url, $body = null)
    {
        return $this->request('put', $url, $body);
    }

    /**
     * @param $url
     * @param null $body
     * @return RestApiResponse
     * @throws NotFoundError
     */
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
        
        if (\array_key_exists($statusCode, $errors)) {
            return \sprintf('%d: %s', $statusCode, $errors[$statusCode]);
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return \sprintf('%d: Unknown 4xx Client Error', $statusCode);
        } else {
            return \sprintf('%d: Unknown HTTP Server Error', $statusCode);
        }
    }

    /**
     * @return resource
     */
    protected function curl()
    {
        if ($this->curl === null) {
            $this->curl = \curl_init($this->baseUrl);
            if (! $this->curl) {
                throw new RuntimeException('CURL INIT ERROR: ' . \curl_error($this->curl));
            }
        }

        return $this->curl;
    }
}
