Issue History and Details
=========================

For convenience, this module comes with a nice overview of your created JIRA
issues. It shows your most recent issues, combined with the most important
details. Please note that only issues created by the configured JIRA user will
be shown.

Issue History Table
-------------------

This table shows three icons in the first column, the related project, issue
type and current state. Please move the mouse over those icons to get more
details. 

[![Issue list and details](screenshot/issue_list_and_details_small.png)](screenshot/issue_list_and_details.png)

The Summary column shows issue summary and description, and the last column
shows how long ago the issue has been created. Move the mouse over the shown
time to read the full related creation time with timezone details.

![JIRA issue list](screenshot/issue_list.png)

You can then choose an issue, and you'll provided with some more details. All
comments posted to this issue will be shown. Also, there are and some links
pointing to JIRA (directly to the issue or to it's project) and to the related
Icinga Host or Service.

![JIRA issue details](screenshot/issue_details.png)


Monitoring module hook
----------------------

It is also possible to filter the issue list by host (and optionally servcie)
name. Not need to manually 

![Monitoring Action Hook](screenshot/monitoring_action_hook.png)

This module hooks into the `monitoring` module and provides so-called Host and
Service Action Hooks. A single click brings you to your Host (or Service) issue
history.
