<?php

/** @var \Icinga\Application\Modules\Module $this */
$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
$this->provideHook('icingadb/HostActions');
$this->provideHook('icingadb/ServiceActions');
$this->provideHook('icingadb/IcingadbSupport');
