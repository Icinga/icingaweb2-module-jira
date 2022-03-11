<?php

namespace Icinga\Module\Jira\Web;

use Exception;
use Icinga\Module\Jira\RestApi;
use ipl\Html\Html;
use ipl\Web\Compat\CompatController;

class Controller extends CompatController
{
    /** @var RestApi */
    private $jira;

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

        $this->controls->prependHtml(Html::tag('h1', null, vsprintf($title, $args)));

        return $this;
    }
}
