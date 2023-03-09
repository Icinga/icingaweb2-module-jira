<?php

namespace Icinga\Module\Jira\Web;

use Exception;
use Icinga\Application\Config;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Jira\RestApi;
use ipl\Html\Html;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\Tabs;

class Controller extends CompatController
{
    /** @var RestApi */
    private $jira;

    protected function getModuleConfig($file = null)
    {
        if ($file === null) {
            if ($this->config === null) {
                $this->config = Config::module($this->getModuleName());
            }
            return $this->config;
        } else {
            if (! array_key_exists($file, $this->configs)) {
                $this->configs[$file] = Config::module($this->getModuleName(), $file);
            }
            return $this->configs[$file];
        }
    }

    protected function dump($what)
    {
        $this->addContent(
            Html::tag('pre', null, print_r($what, true))
        );

        return $this;
    }

    protected function runFailSafe($callable)
    {
        try {
            if (is_array($callable)) {
                return call_user_func($callable);
            } elseif (is_string($callable)) {
                return call_user_func([$this, $callable]);
            } else {
                return $callable();
            }
        } catch (Exception $e) {
            $this->addContent(
                Html::tag('p', ['class' => 'state-hint error'], sprintf(
                    $this->translate('ERROR: %s'),
                    $e->getMessage()
                ))
            );
            // $this->addContent(Html::tag('pre', null, $e->getTraceAsString()));

            return false;
        }
    }

    /**
     * @return RestApi
     */
    protected function jira()
    {
        if ($this->jira === null) {
            $this->jira = $this->connectToJira();
        }

        return $this->jira;
    }

    /**
     * @return RestApi
     */
    protected function connectToJira()
    {
        return RestApi::fromConfig();
    }

    /**
     * Set the window's title and add it as h1 in the controls
     *
     * @param string $title
     * @param mixed ...$args
     *
     * @return $this
     */
    protected function addTitle($title, ...$args)
    {
        $this->setTitle($title, ...$args);

        if (! empty($args)) {
            $title = vsprintf($title, $args);
        }

        $this->controls->prependHtml(Html::tag('h1', null, $title));

        return $this;
    }

    /**
     * Create tabs for a template to edit it and list its fields
     *
     * @param string $name
     *
     * @return Tabs
     *
     * @throws ProgrammingError
     */
    protected function createTemplateTabs(string $name)
    {
        $tabs = $this->getTabs()->add(
            'template',
            [
                'label'     => t('Edit Template'),
                'url'       => Url::fromPath(
                    'jira/template',
                    ['template' => $name]
                )
            ]
        )->add(
            'fields',
            [
                'label'     => t('Fields List'),
                'url'       => Url::fromPath(
                    'jira/fields',
                    ['template' => $name]
                )
            ]
        );

        return $tabs;
    }
}
