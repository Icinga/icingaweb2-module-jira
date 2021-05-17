Icinga Module for JIRA®
=======================

Hassle-free deep integration with Atlassian Jira®. Depending on your needs, this
module is able to:

* create **JIRA Issues for Problems** detected by Icinga
* create **only one** issue per problem
* **acknowledge** Icinga Problems once a JIRA issue has been created
* manually create Host- or Service-related JIRA issues
* shows a Host/Service-related **JIRA Issue History**

And there is more. Use custom templates to trigger JIRA **Workflows** according
your very own needs. This way you can automatically fill JIRA custom fields
based on monitored system properties. This feature is mostly being used to
assign monitored objects to their **related CIs** or to trigger dedicated
**customer-related workflows**.

This is 100% free Open Source Software. Interested? Then let's [get started](doc/01-Introduction.md)!

![JIRA integration](doc/screenshot/issue_list_and_details-new.png)

Changes
-------

### v1.1.0 (unreleased)

* FIX: Render status badge in case there is no related icon (#39)
* FIX: Use the same notification header via Web Form and CLI (#42)

### v1.0.1

* FIX: Ticket URLs pointing to JIRA for Setups sitting in the DocumentRoot (#30)
* FIX: The new HTTP/HTTPS scheme setting didn't work (#30)
* FIX: There still was a dependency on Icinga Director (#28)
* FIX: Form for manually created issues didn't work without Icinga Director (#27)
