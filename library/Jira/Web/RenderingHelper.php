<?php

namespace Icinga\Module\Jira\Web;

use dipl\Html\Html;
use dipl\Html\Link;
use Icinga\Application\Config;
use Icinga\Date\DateFormatter;
use RuntimeException;

class RenderingHelper
{
    public function linkToMonitoring($host, $service)
    {
        if ($service === null) {
            return $this->linkToMonitoringHost($host);
        } else {
            return $this->linkToMonitoringService($host, $service);
        }
    }

    public function linkToMonitoringHost($host)
    {
        return Link::create($host, 'monitoring/host/show', [
            'host' => $host
        ], [
            'class' => 'icon-host',
            'title' => 'Show Icinga Host State',
        ]);
    }

    public function linkToMonitoringService($host, $service)
    {
        return Link::create($service, 'monitoring/service/show', [
            'host'    => $host,
            'service' => $service,
        ], [
            'class' => 'icon-service',
            'title' => 'Show Icinga Service State',
        ]);
    }

    public function linkToJira($caption, $url, $attributes = [])
    {
        $config = Config::module('jira');
        $host = $config->get('api', 'host');
        if ($host === null) {
            throw new RuntimeException('No JIRA host has been configured');
        }
        $baseUrl = sprintf(
            'https://%s:%d/%s',
            $host,
            $config->get('api', 'port', 443),
            trim($config->get('api', 'path', ''), '/')
        );

        if (is_array($url)) {
            $baseUrl .= '/' . implode('/', array_map('urlencode', $url));
        } else {
            $baseUrl .= $url;
        }
        
        $attributes['href'] = $baseUrl;
        $attributes += [
            'target' => '_blank',
            'title'  => 'Open in new JIRA tab'
        ];

        return Html::tag('a', $attributes, $caption)->setSeparator(' ');
    }
    
    public function renderAvatar($object, $width = 16, $height = 16)
    {
        return $this->renderIconImage(
            $object->avatarUrls->{"${width}x${height}"},
            $object->name,
            null,
            $width,
            $height
        );
    }

    public function renderIcon($object)
    {
        return $this->renderIconImage(
            $object->iconUrl,
            $object->name
        );
    }

    public function renderIconImage($url, $title, $alt = null, $width = 16, $height = 16)
    {
        if ($alt === null) {
            $alt = $title;
        }

        return Html::tag('img', [
            'src'    => $url,
            'width'  => $width,
            'height' => $height,
            'alt'    => $alt,
            'title'  => $title,
        ]);
    }

    public function shortTimeSince($time, $onlyTime = true)
    {
        $int = strtotime($time);

        return Html::tag(
            'span',
            ['title' => $time, 'class' => 'time-since'],
            DateFormatter::timeSince(strtotime($time), $onlyTime)
        );
    }

    public function anonymize($test)
    {
        return $test;

        $test = preg_replace_callback(
            '/([A-Z]{4})\-(\d{3,4})/',
            function ($what) {
                return strrev(strtolower($what[1])) . $what[2] . '.example.com';
            },
            $test
        );
        $test = preg_replace('/1\d+\.\d+\./', '192.168.', $test);

        return $test;
    }
}
