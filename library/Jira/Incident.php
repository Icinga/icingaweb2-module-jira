<?php

namespace Icinga\Module\Jira;

class Incident
{
    /** @var Problem */
    protected $problem;

    /** @var Template */
    protected $template;

    protected function __construct(Problem $problem, Template $template)
    {
        $this->problem = $problem;
        $this->template = $template;
    }

    public function getProjectKey()
    {
        return $this->template->get('project_name');
    }

    public function getIssueTypeName()
    {
        return $this->template->get('project_name');
    }

    public function getFields()
    {
        return [
            'project'     => [ 'key' => $this->getProjectKey() ],
            'issue_type'  => [ 'name' => $this->getIssueTypeName() ],
            'summary'     => $this->getSummary(),
            'description' => $this->getDescription(),
        ] + $this->getCustomFields();
    }
    
    public function getCustomFields()
    {
        return $this->template->getFilledCustomFields();
    }

    public function send(RestApi $jira)
    {
        $jira->post('issue', [
            'fields' => $this->getFields()
        ]);
    }
}
