<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Jira\Controllers;

use Icinga\Module\Jira\Web\Controller;

class IndexController extends Controller
{
    public function indexAction()
    {
        $this->redirectNow('jira/issues');
    }
}
