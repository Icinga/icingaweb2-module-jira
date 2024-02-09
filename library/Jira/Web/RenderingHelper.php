<?php

namespace Icinga\Module\Jira\Web;

use Icinga\Application\Config;
use Icinga\Application\Modules\Module;
use Icinga\Date\DateFormatter;
use Icinga\Module\Jira\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Jira\RestApi;
use ipl\Html\Attributes;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\HtmlString;
use ipl\Web\Url;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;
use RuntimeException;

class RenderingHelper
{
    protected $api;

    /** @var ?Link Host link */
    protected $hostLink;

    /** @var ?Link Service link */
    protected $serviceLink;

    /**
     * Set the link of monitored host
     *
     * @param Link $hostLink
     *
     * @return $this
     */
    public function setHostLink(Link $hostLink): self
    {
        $this->hostLink = $hostLink;

        return $this;
    }

    /**
     * Set the link of monitored service
     *
     * @param Link $serviceLink
     *
     * @return $this
     */
    public function setServiceLink(Link $serviceLink): self
    {
        $this->serviceLink = $serviceLink;

        return $this;
    }

    /**
     * Get the link of monitored host
     *
     * @return ?Link
     */
    public function getHostLink(): ?Link
    {
        return $this->hostLink;
    }

    /**
     * Get the link of monitored service
     *
     * @return ?Link
     */
    public function getServiceLink(): ?Link
    {
        return $this->serviceLink;
    }


    /**
     * Get the formatted issue comment using author, time and description or comment body
     *
     * @param string|HtmlElement[] $author
     * @param string $time
     * @param string $body
     *
     * @return HtmlElement[]
     */
    public function getIssueComment($author, string $time, string $body): array
    {
        return [
            new HtmlElement('h3', null, Html::sprintf('%s: %s', $this->shortTimeSince($time), $author)),
            new HtmlElement('pre', new Attributes(['class' => 'comment']), $this->formatBody($body)),
        ];
    }

    /**
     * Format the given issue description or comment body
     *
     * @param string $body
     *
     * @return HtmlString
     */
    public function formatBody(string $body): HtmlString
    {
        $html = Html::wantHtml($body)->render();

        // This is safe.
        return new HtmlString($this->replaceLinks($html) ?? '');
    }

    /**
     * Replace object urls in the given string with link elements
     *
     * @param string $string
     *
     * @return ?string
     */
    protected function replaceLinks(string $string): ?string
    {
        return preg_replace_callback('/\[([^|]+)\|([^]]+)]/', function ($match) {
            $url = Url::fromPath(htmlspecialchars_decode($match[2]));
            $link = new Link(
                $match[1],
                $url,
                ['target' => '_blank']
            );

            if ($url->hasParam('service') || $url->hasParam('host.name')) {
                if (
                    strpos($match[2], 'icingaweb2/monitoring') !== false
                    && (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend())
                ) {
                    $link = new Link(
                        $match[1],
                        Url::fromPath(
                            'icingadb/service',
                            [
                                'name'      => $url->getParam('service'),
                                'host.name' => $url->getParam('host'),
                            ]
                        ),
                        ['target' => '_blank']
                    );
                }

                $serviceLink = clone $link;
                $serviceLink->setContent([new Icon('cog'), $match[1]])
                    ->addAttributes(['title' => t('Show Icinga Service State')]);
                $this->setServiceLink($serviceLink);
            } else {
                $icon = new Icon('server');
                if (strpos($match[2], 'icingaweb2/monitoring') !== false) {
                    if (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend()) {
                        $link = new Link(
                            $match[1],
                            Url::fromPath(
                                'icingadb/host',
                                ['name' => $url->getParam('host')]
                            ),
                            ['target' => '_blank']
                        );
                    } else {
                        $icon = new Icon('laptop');
                    }
                }

                $hostLink = clone $link;
                $hostLink->setContent([$icon, $match[1]])
                    ->addAttributes(['title' => t('Show Icinga Host State')]);
                $this->setHostLink($hostLink);
            }

            return $link->render();
        }, $string);
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
            $object->avatarUrls->{"{$width}x{$height}"},
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

    protected function api()
    {
        if ($this->api === null) {
            $this->api = RestApi::fromConfig();
        }

        return $this->api;
    }
}
