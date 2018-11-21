<?php

namespace Icinga\Module\Jira\Web;

use dipl\Html\Html;
use dipl\Web\CompatController;
use Exception;
use Icinga\Module\Jira\RestApi;

class Controller extends CompatController
{
    /** @var RestApi */
    private $jira;

    protected function dump($what)
    {
        $this->content()->add(
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
            $this->content()->add([
                Html::tag('p', ['class' => 'state-hint error'], sprintf(
                    $this->translate('ERROR: %s'),
                    $e->getMessage()
                ))
            ]);
            $this->content()->add(Html::tag('pre', null, $e->getTraceAsString()));
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
}
