<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Forms\Config;

use Icinga\Application\Config;
use Icinga\Module\Jira\RestApi;
use Icinga\Web\Session;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Validator\CallbackValidator;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;

class FieldConfigForm extends CompatForm
{
    use CsrfCounterMeasure;

    /** @var RestApi */
    private $jira;

    /** @var array|null */
    protected $fields = [];

    /** @var Config */
    protected $templateConfig;

    /** @var string */
    protected $fieldValue;

    /** @var string */
    protected $templateName;

    /** @var bool Hack used for delete button */
    protected $callOnSuccess;

    /** @var string */
    protected $fieldId;

    public function __construct(RestApi $jira, string $templateName, $fieldId = null)
    {
        $this->jira = $jira;
        $this->fields = $this->enumAllowedFields();

        $this->templateConfig = Config::module('jira', 'templates');

        $this->templateName = $templateName;

        if ($fieldId !== null) {
            if (! array_key_exists($fieldId, $this->fields)) {
                $this->fieldId = array_search($fieldId, $this->fields);
            } else {
                $this->fieldId = $fieldId;
            }

            $templateFields = $this->templateConfig->getSection($templateName)->toArray();

            $this->fieldValue = $templateFields[$fieldId];
        }
    }

    /**
     * Returns fieldId-fieldLabel pairs of all the custom fields including duedate field
     *
     * Supported field types are string, array, number and date
     *
     * @return array
     *
     * @throws \Icinga\Exception\NotFoundError
     */
    public function enumAllowedFields(): array
    {
        $fieldTypes = ['string', 'number', 'array', 'date'];
        $fields = [];

        foreach ($this->jira->get('field')->getResult() as $field) {
            if ($field->custom && in_array($field->schema->type, $fieldTypes)) {
                if ($field->schema->type === 'array' && $field->schema->items !== 'string') {
                    continue;
                }

                $fields[$field->id] = $field->name;
            }
        }

        $fields['duedate'] = 'Due Date';

        $icingaKey = Config::module('jira')
            ->get('key_fields', 'icingaKey', 'icingaKey');

        $icingaStatus = Config::module('jira')
            ->get('key_fields', 'icingaStatus', 'icingaStatus');

        if (($key = array_search($icingaKey, $fields)) !== false) {
            unset($fields[$key]);
        }

        if (($status = array_search($icingaStatus, $fields)) !== false) {
            unset($fields[$status]);
        }

        return $fields;
    }

    protected function assemble()
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));

        $this->addElement(
            'select',
            'fields',
            [
                'label'      => $this->translate('Jira Field'),
                'class'      => 'autosubmit',
                'options'    => $this->optionalEnum($this->fields),
                'required'   => true,
                'validators' => [
                    'Callback' => function ($value, $validator) {
                        /** @var CallbackValidator $validator */
                        $templateFieldKeys = $this->templateConfig->getSection($this->templateName)->keys();
                        $selected = $this->fields[$value];

                        if (
                            $value !== $this->fieldId
                            && (in_array($value, $templateFieldKeys) || in_array($selected, $templateFieldKeys))
                        ) {
                            $validator->addMessage(sprintf(
                                $this->translate('Field "%s" already exists in the template "%s"'),
                                $selected,
                                $this->templateName
                            ));

                            return false;
                        }

                        return true;
                    }
                ]
            ]
        );

        if ($this->fieldId !== null) {
            $this->getElement('fields')
                ->setValue($this->fieldId)
                ->addAttributes(['disabled' => true]);
        }

        $isFieldsDisabled = $this->getElement('fields')
            ->getAttributes()
            ->get('disabled');

        if (($this->hasBeenSent() || $isFieldsDisabled) && $this->getValue('fields') !== null) {
            $fieldsAssociation = [
                'hostgroup'    => $this->translate('Host Group'),
                'servicegroup' => $this->translate('Service Group'),
                'customvar'    => $this->translate('Custom Variable'),
            ];

            $this->addElement(
                'text',
                'type',
                [
                    'label'    => $this->translate('Field Type'),
                    'disabled' => true,
                    'required' => true,
                ]
            );

            $fieldDetails = $this->jira->getJiraFieldInfo($this->getValue('fields'));
            $this->getElement('type')->setValue($fieldDetails->schema->type);

            if ($this->getValue('type') !== 'array') {
                $fieldsAssociation['other'] = $this->translate('Others');
            }

            $this->addElement(
                'select',
                'associated',
                [
                    'label'      => $this->translate('Associated Icinga Object Property'),
                    'class'      => 'autosubmit',
                    'options'    => $this->optionalEnum($fieldsAssociation),
                    'required'   => true,
                    'validators' => [
                        'Callback' => function ($value, $validator) use ($fieldsAssociation) {
                            /** @var CallbackValidator $validator */
                            if (
                                ($value === 'hostgroup' || $value === 'servicegroup')
                                && $this->getValue('type') !== 'array'
                            ) {
                                $validator->addMessage(sprintf(
                                    $this->translate('%s can only be an array type field.'),
                                    $fieldsAssociation[$value]
                                ));

                                return false;
                            }

                            return true;
                        }
                    ]
                ]
            );

            if ($this->fieldId !== null && ! $this->hasBeenSent()) {
                if (preg_match('/\${([^}\s]+)}/', $this->fieldValue, $matches)) {
                    if (preg_match('/^(?:host|service)\./', $matches[1])) {
                        $this->getElement('associated')->setValue('customvar');
                    } elseif ($matches[1] === 'hostgroup' || $matches[1] === 'servicegroup') {
                        $this->getElement('associated')->setValue($matches[1]);
                    } else {
                        $this->getElement('associated')->setValue('other');
                    }
                } else {
                    $this->getElement('associated')->setValue('other');
                }
            }

            if (($this->hasBeenSent() || $this->fieldId !== null)) {
                if ($this->getValue('associated') === 'customvar') {
                    $this->addElement(
                        'text',
                        $this->getValue('fields') . '_cv',
                        [
                            'label'       => $this->translate('Custom Variable'),
                            'required'    => true,
                            'description' => $this->translate(
                                'Enter appropriate custom variable. For example host.vars.customvar or'
                                . ' service.vars.customvar.'
                            ),
                            'placeholder' => 'host.vars.customvar / service.vars.customvar'
                        ]
                    );
                }

                if ($this->getValue('associated') === 'other') {
                    $this->addElement(
                        'text',
                        $this->getValue('fields') . '_value',
                        [
                            'label'       => $this->translate('Field Value'),
                            'required'    => true,
                            'description' => $this->translate(
                                'Enter the value for the field.'
                            ),
                            'validators'  => [
                                'Callback' => function ($value, $validator) use ($fieldsAssociation) {
                                    /** @var CallbackValidator $validator */

                                    if ($value !== null) {
                                        if (
                                            $this->getValue('type') === 'number'
                                            && ! is_numeric($value)
                                        ) {
                                            $validator->addMessage(sprintf(
                                                $this->translate('Field %s expects a numeric value.'),
                                                $this->enumAllowedFields()[$this->getValue('fields')]
                                            ));

                                            return false;
                                        }

                                        if (
                                            $this->getValue('type') === 'date'
                                            && ! strtotime($value)
                                        ) {
                                            $validator->addMessage(sprintf(
                                                $this->translate('Field %s expects english textual datetime.'),
                                                $this->enumAllowedFields()[$this->getValue('fields')]
                                            ));

                                            return false;
                                        }

                                        if (preg_match('/\${([^}\s]+)}/', $value, $matches)) {
                                            if (preg_match('/^(?:host|service)\./', $matches[1])) {
                                                $validator->addMessage(sprintf(
                                                    $this->translate('Field %s cannot be a custom variable.'),
                                                    $this->enumAllowedFields()[$this->getValue('fields')]
                                                ));

                                                return false;
                                            } elseif ($matches[1] === 'hostgroup' || $matches[1] === 'servicegroup') {
                                                $validator->addMessage(sprintf(
                                                    $this->translate('Field %s cannot be %s.'),
                                                    $this->enumAllowedFields()[$this->getValue('fields')],
                                                    strtolower($fieldsAssociation[$matches[1]])
                                                ));

                                                return false;
                                            }
                                        }
                                    }

                                    return true;
                                }
                            ]
                        ]
                    );
                }

                if (! $this->hasBeenSent() && $this->fieldId !== null) {
                    if ($this->getValue('associated') === 'other') {
                        $this->getElement($this->getValue('fields') . '_value')
                            ->setValue($this->fieldValue);
                    }

                    if ($this->getValue('associated') === 'customvar') {
                        $this->getElement($this->getValue('fields') . '_cv')
                            ->setValue($matches[1]);
                    }
                }
            }
        }

        $this->addElement(
            'submit',
            'submit',
            [
                'label' => $this->fieldId ? $this->translate('Edit Field') : $this->translate('Add Field')
            ]
        );

        if ($this->fieldId !== null) {
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

            if ($deleteButton->hasBeenPressed()) {
                $templateFields =  $this->templateConfig->getSection($this->templateName)->toArray();

                $field = isset($templateFields[$this->fieldId]) ? $this->fieldId : $this->fields[$this->fieldId];

                unset($templateFields[$field]);

                $this->templateConfig->setSection($this->templateName, $templateFields);
                $this->templateConfig->saveIni();
                $this->getSubmitButton()->setValue($this->getSubmitButton()->getButtonLabel());

                $this->callOnSuccess = false;
                $this->valid = true;

                return;
            }
        }
    }

    /**
     * Appends a null option to the given key-value pairs
     *
     * @param $enum
     * @param $nullLabel
     *
     * @return array|null[]
     */
    public function optionalEnum($enum, $nullLabel = null)
    {
        if ($nullLabel === null) {
            $nullLabel = $this->translate('- please choose -');
        }

        return [null => $nullLabel] + $enum;
    }

    public function onSuccess()
    {
        if ($this->callOnSuccess === false) {
            $this->getPressedSubmitElement()->setValue($this->getElement('delete')->getLabel());

            return;
        }

        $fields = $this->templateConfig->getSection($this->templateName)->toArray();

        $fieldId = $this->getValue('fields');
        $fieldName = isset($fields[$fieldId]) ? $fieldId : $this->fields[$fieldId];
        $associated = $this->getValue('associated');

        if ($associated === 'hostgroup' || $associated === 'servicegroup') {
            $fieldValue = '${' . $associated . '}';
        } elseif ($associated === 'customvar') {
            $fieldValue = $this->getValue($fieldId . '_cv');
            $fieldValue = '${' . $fieldValue . '}';
        } else {
            $fieldValue = $this->getValue($fieldId . '_value');
        }

        $fields[$fieldName] = $fieldValue;

        $this->templateConfig->setSection($this->templateName, $fields);
        $this->templateConfig->saveIni();
    }
}
