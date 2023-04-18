<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Forms\Config;

use Icinga\Application\Config;
use Icinga\Web\Session;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Validator\CallbackValidator;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;

class TemplateConfigForm extends CompatForm
{
    use CsrfCounterMeasure;

    /** @var Config */
    protected $config;

    /** @var string|null */
    protected $templateName;

    public function __construct($templateName = null)
    {
        $this->config = Config::module('jira', 'templates');

        $this->templateName = $templateName;

        if ($this->templateName !== null) {
            $this->populate(['template' => $templateName]);
        }
    }

    protected function assemble()
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));

        $this->addElement(
            'text',
            'template',
            [
                'label'      => $this->translate('Template'),
                'required'   => true,
                'validators' => [
                    'Callback' => function ($value, $validator) {
                        /** @var CallbackValidator $validator */
                        if ($value !== $this->templateName && in_array($value, $this->config->keys())) {
                            $validator->addMessage(sprintf(
                                $this->translate('Template with name "%s" already exists'),
                                $value
                            ));

                            return false;
                        }

                        return true;
                    }
                ]
            ]
        );

        $this->addElement(
            'submit',
            'submit',
            [
                'label' => $this->templateName ? $this->translate('Save Changes') : $this->translate('Add Template')
            ]
        );

        if ($this->templateName !== null) {
            /** @var FormSubmitElement $deleteButton */
            $deleteButton = $this->createElement(
                'submit',
                'delete',
                [
                    'label'          => $this->translate('Delete'),
                    'class'          => 'btn-remove',
                    'formnovalidate' => true
                ]
            );

            $this->registerElement($deleteButton);
            $this->getElement('submit')
                ->getWrapper()
                ->prepend($deleteButton);
        }
    }

    public function hasBeenSubmitted()
    {
        if ($this->getPressedSubmitElement() !== null && $this->getPressedSubmitElement()->getName() === 'delete') {
            return true;
        }

        return parent::hasBeenSubmitted();
    }

    public function isValid()
    {
        if ($this->getPressedSubmitElement()->getName() === 'delete') {
            $csrfElement = $this->getElement('CSRFToken');

            if (! $csrfElement->isValid()) {
                return false;
            }

            return true;
        }

        return parent::isValid();
    }

    public function onSuccess()
    {
        if ($this->getPressedSubmitElement()->getName() === 'delete') {
            $this->config->removeSection($this->templateName);
            $this->config->saveIni();

            return;
        }

        $templateConfig = Config::fromIni($this->config->getConfigFile());
        $value = $this->getValue('template');

        if ($this->templateName !== null && $value !== $this->templateName) {
            $template = $templateConfig->getSection($this->templateName);
            $templateConfig->removeSection($this->templateName);
            $templateConfig->setSection($value, $template);
        } else {
            $template = $templateConfig->getSection($value);
            $templateConfig->setSection($this->getValue('template'), $template);
        }

        $templateConfig->saveIni($templateConfig->getConfigFile());
    }
}
