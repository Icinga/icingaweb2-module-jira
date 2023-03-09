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
        'title' => $this->translate('Configuration'),
        'label' => $this->translate('Configuration'),
        'url'   => 'configuration'
    ]
);

$this->provideConfigTab(
    'templates',
    [
        'title' => $this->translate('Configure templates'),
        'label' => $this->translate('Templates Configuration'),
        'url'   => 'templates'
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
