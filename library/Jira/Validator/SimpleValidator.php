<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Jira\Validator;

use ipl\Stdlib\Contract\ValidatorInterface;
use ipl\Stdlib\MessageContainer;

abstract class SimpleValidator implements ValidatorInterface
{
    use MessageContainer;

    protected $settings = [];

    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    public function getSetting($name, $default = null)
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        } else {
            return $default;
        }
    }
}
