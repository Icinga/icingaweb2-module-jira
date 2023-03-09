<?php

namespace Icinga\Module\Jira\Web;

use Icinga\Application\Config;
use Icinga\Application\Modules\Module;
use Icinga\Date\DateFormatter;
use Icinga\Module\Jira\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Jira\RestApi;
use ipl\Html\Html;
use ipl\Web\Url;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;
use RuntimeException;

class RenderingHelper
{
    protected $api;

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
        if (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend()) {
            return new Link([new Icon('server'), $host], Url::fromPath('icingadb/host', [
                'name' => $host
            ]), [
                'title' => t('Show Icinga Host State'),
            ]);
        }

        return new Link([new Icon('laptop'), $host], Url::fromPath('monitoring/host/show', [
            'host' => $host
        ]), [
            'title' => t('Show Icinga Host State'),
        ]);
    }

    public function linkToMonitoringService($host, $service)
    {
        if (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend()) {
            return new Link([new Icon('cog'), $service], Url::fromPath('icingadb/service', [
                'name'          => $service,
                'host.name'     => $host,
            ]), [
                'title' => t('Show Icinga Service State'),
            ]);
        }

        return new Link([new Icon('cog'), $service], Url::fromPath('monitoring/service/show', [
            'host'    => $host,
            'service' => $service,
        ]), [
            'title' => t('Show Icinga Service State'),
        ]);
    }

    public function linkToJira($caption, $url, $attributes = [])
    {
        $config = Config::module('jira');
        $host = $config->get('api', 'host');
        if ($host === null) {
            throw new RuntimeException('No Jira host has been configured');
        }
        if (is_array($url)) {
            $url = implode('/', array_map('urlencode', $url));
        }

        $attributes['href'] = $this->api()->urlLink($url);
        $attributes += [
            'target' => '_blank',
            'title'  => 'Open in new Jira tab'
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

    public function renderStatusBadge($status)
    {
        // TODO: a color map for $status->statusCategory->colorName could be helpful
        if (substr($status->iconUrl, -1) === '/') {
            if (isset($status->statusCategory->name)) {
                return Html::tag('span', [
                    'class' => 'badge status-badge',
                ], $status->statusCategory->name);
            }
        }

        return [$this->renderIcon($status), ' ', $status->name];
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

    protected function api()
    {
        if ($this->api === null) {
            $this->api = RestApi::fromConfig();
        }

        return $this->api;
    }
}
