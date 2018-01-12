<?php

namespace Icinga\Module\Jira\Web;

use dipl\Html\Html;
use dipl\Html\Link;
use Icinga\Date\DateFormatter;

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
        $baseUrl = 'https://service.itenos.de/';
        if (is_array($url)) {
            $baseUrl .= array_shift($url)
                . '/'
                . implode('/', array_map('urlencode', $url));
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
}
