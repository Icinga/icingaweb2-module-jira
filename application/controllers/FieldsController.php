<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Controllers;

use Icinga\Module\Jira\Forms\Config\FieldConfigForm;
use Icinga\Module\Jira\Web\Controller;
use Icinga\Web\Notification;
use ipl\Web\Url;

class FieldsController extends Controller
{
    public function init()
    {
        $this->assertPermission('config/modules');
        parent::init();
    }

    public function addAction()
    {
        $this->addTitleTab(t('Add Custom Field'));

        $template = $this->params->getRequired('template');

        $form = (new FieldConfigForm($this->jira(), $template))
            ->on(FieldConfigForm::ON_SUCCESS, function ($form) use ($template) {
                Notification::success(sprintf(
                    t('Added custom field "%s" successfully to the template "%s"'),
                    $form->enumAllowedFields()[$form->getValue('fields')],
                    $template
                ));
                $this->redirectNow(Url::fromPath('jira/templates/edit', ['template' => $template]));
            })->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }

    public function editAction()
    {
        $this->addTitleTab(t('Edit Custom Field'));

        $fieldId = $this->params->shift('fieldId');
        $template = $this->params->shift('template');

        $form = (new FieldConfigForm($this->jira(), $template, $fieldId))
            ->on(FieldConfigForm::ON_SUCCESS, function ($form) use ($template, $fieldId) {
                if ($form->getPressedSubmitElement()->getName() === 'delete') {
                    Notification::success(sprintf(
                        t('Removed field "%s" successfully from template "%s"'),
                        $fieldId,
                        $template
                    ));
                    $this->redirectNow(Url::fromPath(
                        'jira/templates/edit',
                        ['template' => $template]
                    ));
                } else {
                    Notification::success(sprintf(
                        t('Updated field "%s" successfully in template "%s"'),
                        $fieldId,
                        $template
                    ));
                    $this->redirectNow(Url::fromPath(
                        'jira/fields/edit',
                        [
                            'template' => $template,
                            'fieldId'  => $fieldId
                        ]
                    ));
                }
            })->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }
}
