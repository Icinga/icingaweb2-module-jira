<?php

// Icinga Web JIRA Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Forms\Config;

use Icinga\Application\Config;
use Icinga\Module\Jira\RestApi;
use Icinga\Web\Notification;
use ipl\Validator\CallbackValidator;
use ipl\Web\Compat\CompatForm;

class ConfigForm extends CompatForm
{
    protected function assemble()
    {
        $this->addElement('select', 'type', [
            'class'         => 'autosubmit',
            'label'         => t('Deployment Type'),
            'description'   => t('Select deployment type'),
            'required'      => true,
            'options'       => [
                'cloud'  => t('Cloud'),
                'server' => t('Server')
            ],
            'validators'    => [
                'Callback' => function ($value, $validator) {
                    /** @var CallbackValidator $validator */
                    $serverInfo = RestApi::fromConfig()->getServerInfo();

                    if ($value !== strtolower($serverInfo->deploymentType)) {
                        $validator->addMessage(t('Jira Software seems to be deployed differently.'));
                        return false;
                    }

                    return true;
                }
            ]
        ]);

        if ($this->getValue('type') === 'cloud') {
            $this->addElement('checkbox', 'legacy', [
                'label'             => t('Legacy compatibility'),
                'description'       => t('Enable legacy compatibility if unable to create jira issues'),
                'class'             => 'autosubmit',
                'checkedValue'      => '1',
                'uncheckedValue'    => '0'
            ]);
        }

        $this->addElement('submit', 'btn_submit', [
            'label' => t('Save Changes')
        ]);
    }
}
