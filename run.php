<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/** @var \Icinga\Application\Modules\Module $this */
$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
$this->provideHook('icingadb/HostActions');
$this->provideHook('icingadb/ServiceActions');
$this->provideHook('icingadb/IcingadbSupport');
