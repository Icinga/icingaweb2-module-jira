<?php

namespace Icinga\Module\Jira;

use Icinga\Application\Config;

class LinkHelper
{
    protected static $icingaUrl;

    public static function jiraLink($label, $url)
    {
        return \sprintf('[%s|%s]', $label, $url);
    }

    public static function linkToIcingaHost($hostname)
    {
        if (($url = self::getIcingaWebUrl()) === null) {
            return $hostname;
        }

        return LinkHelper::jiraLink($hostname, "$url/monitoring/host/show?host=" . \rawurlencode($hostname));
    }

    public static function linkToIcingadbHost($hostname)
    {
        if (($url = self::getIcingaWebUrl()) === null) {
            return $hostname;
        }

        return LinkHelper::jiraLink($hostname, "$url/icingadb/host?name=" . \rawurlencode($hostname));
    }

    public static function linkToIcingaService($hostname, $service)
    {
        if (($url = self::getIcingaWebUrl()) === null) {
            return $service;
        }

        return LinkHelper::jiraLink($service, \sprintf(
            "$url/monitoring/service/show?host=%s&service=%s",
            \rawurlencode($hostname),
            \rawurlencode($service)
        ));
    }

    public static function linkToIcingadbService($hostname, $service)
    {
        if (($url = self::getIcingaWebUrl()) === null) {
            return $service;
        }

        return LinkHelper::jiraLink($service, \sprintf(
            "$url/icingadb/service?name=%s&host.name=%s",
            \rawurlencode($service),
            \rawurlencode($hostname)
        ));
    }

    public static function getIcingaWebUrl()
    {
        if (self::$icingaUrl === null) {
            if ($url = Config::module('jira')->get('icingaweb', 'url')) {
                self::$icingaUrl = \rtrim($url, '/');
            } else {
                self::$icingaUrl = false;
            }
        }

        if (self::$icingaUrl === false) {
            return null;
        }

        return self::$icingaUrl;
    }
}
