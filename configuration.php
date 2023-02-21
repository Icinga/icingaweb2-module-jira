<?php

/** @var \Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('Jira'))
    ->setUrl('jira')
    ->setPriority(63)
    ->setIcon('tasks');
$section->add(N_('Issues'))->setUrl('jira/issues')->setPriority(10);

$this->providePermission('jira/issue/create', $this->translate('Allow to manually create issues'));

$this->provideConfigTab(
    'deployment',
    [
        'label' => t('Configuration'),
        'url'   => 'configuration'
    ]
);

if ($this->app->getModuleManager()->hasEnabled('director')) {
    $this->provideConfigTab(
        'director',
        [
            'label' => t('Director Config'),
            'title' => t('Director Config Preview'),
            'url'   => 'configuration/director'
        ]
    );
}
