<?php

namespace Icinga\Module\Jira\Controllers;

use Icinga\Module\Jira\Web\Controller;

class IndexController extends Controller
{
    public function indexAction()
    {
        $this->redirectNow('jira/issues');
    }
}
