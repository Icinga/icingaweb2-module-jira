<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Jira\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\HostActionsHook;
use Icinga\Module\Icingadb\Model\Host;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class HostActions extends HostActionsHook
{
    public function getActionsForObject(Host $host): array
    {
        return [
            new Link(
                'Jira Issues',
                Url::fromPath(
                    'jira/issues',
                    [
                        'host'  => $host->name,
                        'all'   => true
                    ]
                )
            )
        ];
    }
}
