<?php

// Icinga Web Jira Integration | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Jira\Controllers;

use Icinga\Module\Jira\Forms\Config\TemplateConfigForm;
use Icinga\Module\Jira\Web\Controller;
use Icinga\Web\Notification;
use ipl\Web\Url;

class TemplateController extends Controller
{
    public function init()
    {
        $this->assertPermission('config/modules');
        parent::init();
    }

    public function indexAction()
    {
        $template = $this->params->get('template');

        $this->createTemplateTabs($template)->activate('template');

        $form = (new TemplateConfigForm($template))
            ->on(TemplateConfigForm::ON_SUCCESS, function ($form) use ($template) {
                if ($form->getPressedSubmitElement()->getName() === 'delete') {
                    Notification::success(sprintf(
                        t('Deleted template "%s" successfully'),
                        $template
                    ));
                } else {
                    Notification::success(sprintf(
                        t('Updated template name successfully from "%s" to "%s"'),
                        $template,
                        $form->getValue('template')
                    ));
                }

                $this->redirectNow(Url::fromPath('jira/templates'));
            })->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }
}
