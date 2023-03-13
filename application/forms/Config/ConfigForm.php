<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Forms\Config;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Jira\RestApi;
use Icinga\Web\Session;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Validator\CallbackValidator;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;

class ConfigForm extends CompatForm
{
    use CsrfCounterMeasure;

    protected function assemble()
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));

        // Fieldset for API
        $api = (new FieldsetElement(
            'api',
            [
                'label' => $this->translate('API Configuration'),
            ]
        ));

        $this->addElement($api);

        $api->addElement(
            'text',
            'host',
            [
                'label'       => $this->translate('Host'),
                'description' => $this->translate('Jira host name or IP address'),
                'required'    => true,
            ]
        )->addElement(
            'text',
            'port',
            [
                'label' => $this->translate('Port'),
            ]
        )->addElement(
            'text',
            'path',
            [
                'label' => $this->translate('Path'),
            ]
        )->addElement(
            'text',
            'scheme',
            [
                'label'       => $this->translate('Scheme'),
                'description' => $this->translate('Protocol used by Jira (http / https)'),
            ]
        );

        $this->addElement(
            'text',
            'username',
            [
                'label'    => $this->translate('Username'),
                'required' => true,
            ]
        );

        $this->addElement(
            'password',
            'password',
            [
                'label'    => $this->translate('Password'),
                'required' => true,
            ]
        );

        // Fieldset for deployment type
        $deployment = (new FieldsetElement(
            'deployment',
            [
                'label'       => $this->translate('Deployment Settings'),
                'description' => $this->translate('Configure where the Jira software has been deployed.')
            ]
        ));

        $this->addElement($deployment);

        $apiValues = $this->getValue('api');

        $deployment->addElement(
            'select',
            'type',
            [
                'class'         => 'autosubmit',
                'label'         => $this->translate('Deployment Type'),
                'required'      => true,
                'options'       => [
                    'cloud'  => $this->translate('Cloud'),
                    'server' => $this->translate('Server')
                ],
                'value'         => 'cloud',
                'validators'    => [
                    'Callback' => function ($value, $validator) use ($apiValues) {
                        /** @var CallbackValidator $validator */
                        $password = $this->getValue('password');
                        if ($password !== null) {
                            $apiValues['username'] = $this->getValue('username');
                            $apiValues['password'] = $password;
                            Config::module('jira')
                                ->setSection('api', $apiValues);
                        }

                        try {
                            $serverInfo = RestApi::fromConfig()->getServerInfo();
                        } catch (Exception $e) {
                            $validator->addMessage($this->translate($e->getMessage()));

                            return false;
                        }

                        if ($value !== strtolower($serverInfo->deploymentType)) {
                            $validator->addMessage($this->translate('Jira Software seems to be deployed differently.'));

                            return false;
                        }

                        return true;
                    }
                ]
            ]
        );

        if ($deployment->getValue('type') === 'cloud') {
            $deployment->addElement(
                'checkbox',
                'legacy',
                [
                    'label'          => $this->translate('Legacy compatibility'),
                    'class'          => 'autosubmit',
                    'checkedValue'   => '1',
                    'uncheckedValue' => '0',
                    'value'          => '1'
                ]
            );
        }

        // Fieldset for Jira Key Fields
        $keyFields = (new FieldsetElement(
            'key_fields',
            [
                'label'       => $this->translate('Key Fields'),
                'description' => $this->translate(
                    'The module requires you to create two custom fields in Jira that represent '
                    . '"icingaKey" and "icingaStatus".'
                )
            ]
        ));

        $this->addElement($keyFields);

        $keyFields->addElement(
            'text',
            'icingaKey',
            [
                'label'       => 'icingaKey',
                'description' => $this->translate(
                    'Custom field to figure out whether an issue for the given object already exists'
                ),
                'required'    => true,
                'value'       => $this->getElement('key_fields')->getValue('icingaKey') ?? 'icingaKey'
            ]
        )->addElement(
            'text',
            'icingaStatus',
            [
                'label'       => 'icingaStatus',
                'description' => $this->translate(
                    'Custom field that represents the status of icinga object'
                ),
                'required'    => true,
                'value'       => $this->getElement('key_fields')->getValue('icingaStatus') ?? 'icingaStatus',
                'validators'  => [
                    'Callback' => function ($value, $validator) {
                        /** @var CallbackValidator $validator */

                        if ($value === $this->getElement('key_fields')->getValue('icingaKey')) {
                            $validator->addMessage($this->translate(
                                'icingaStatus and icingaKey cannot be the same field.'
                            ));

                            return false;
                        }

                        return true;
                    }
                ]
            ]
        );

        // Fieldset for UI
        $ui = (new FieldsetElement(
            'ui',
            [
                'label'       => $this->translate('Issue Defaults'),
                'description' => $this->translate(
                    'Default project and issue type to be used for creating Jira tickets.'
                    . ' Please note that the project settings must represent project keys, not display names.'
                )
            ]
        ));

        $this->addElement($ui);

        $ui->addElement(
            'text',
            'project',
            [
                'label' => $this->translate('Project'),
            ]
        )->addElement(
            'text',
            'issuetype',
            [
                'label' => $this->translate('Issue Type'),
            ]
        );

        // Fieldset for Icinga web Url
        $iw = (new FieldsetElement(
            'icingaweb',
            [
                'label'       => $this->translate('Icinga Web Link'),
                'description' => $this->translate(
                    'If you want to have links pointing back to your Icinga Installation in your Jira issues, '
                    . 'you need to fill the Url setting.'
                )
            ]
        ));

        $this->addElement($iw);

        $iw->addElement(
            'text',
            'url',
            [
                'label' => $this->translate('URL'),
            ]
        );

        $this->addElement(
            'submit',
            'submit',
            [
                'label' => $this->translate('Save Changes')
            ]
        );
    }

    /**
     * Get the values for all but ignored elements
     *
     * Username and Password are treated as elements under api fieldset
     *
     * @return array Values as name-value pairs
     */
    public function getValues()
    {
        $values = parent::getValues();
        $values['api']['username'] = $values['username'];
        $values['api']['password'] = $values['password'];

        unset($values['username'], $values['password']);

        return $values;
    }

    /**
     * Populate values of registered elements of ConfigForm
     *
     * Username and Password are separated from api section
     *
     * @param $values
     *
     * @return $this
     */
    public function populate($values)
    {
        if ($values instanceof Config) {
            $values = $values->toArray();
        }

        if (isset($values['api'])) {
            $api = $values['api'];

            $values['username'] = $api['username'] ?? $values['username'];
            $values['password'] = $api['password'] ?? $values['password'];

            unset($api['username']);
            unset($api['password']);

            $values['api'] = $api;
        }

        parent::populate($values);

        return $this;
    }
}
