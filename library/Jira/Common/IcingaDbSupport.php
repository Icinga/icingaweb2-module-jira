<?php

// Icinga Web Jira Integration | (c) 2024 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Common;

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use Icinga\Authentication\Auth;

trait IcingaDbSupport
{
    /**
     * Whether to use icingadb as the backend
     *
     * @return bool Returns true if the monitoring module does not exist or  if the user does not have permissions to
     *              the monitoring module
     */
    protected function useIcingaDbAsBackend(): bool
    {
        $user = Auth::getInstance()->getUser();
        $authenticated = Icinga::app()->isWeb() && Auth::getInstance()->isAuthenticated();

        return ! Module::exists('monitoring')
            || ($authenticated && $user && ! $user->can('modules/monitoring'));
    }
}
