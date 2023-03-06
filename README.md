# Icinga Web Jira Integration

[![PHP Support](https://img.shields.io/badge/php-%3E%3D%207.2-777BB4?logo=PHP)](https://php.net/)
![Build Status](https://github.com/icinga/icingaweb2-module-jira/workflows/PHP%20Tests/badge.svg?branch=master)
[![Github Tag](https://img.shields.io/github/tag/Icinga/icingaweb2-module-jira.svg)](https://github.com/Icinga/icingaweb2-module-jira)

![Icinga Logo](https://icinga.com/wp-content/uploads/2014/06/icinga_logo.png)

Hassle-free deep integration with Atlassian Jira™. Depending on your needs, this
module is able to:

* create **Jira Issues for Problems** detected by Icinga
* create **only one** issue per problem
* **acknowledge** Icinga Problems once a Jira issue has been created
* manually create Host- or Service-related Jira issues
* shows a Host/Service-related **Jira Issue History**

And there is more. Use custom templates to trigger Jira **Workflows** according
your very own needs. This way you can automatically fill Jira custom fields
based on monitored system properties. This feature is mostly being used to
assign monitored objects to their **related CIs** or to trigger dedicated
**customer-related workflows**.

Let's [get started](doc/01-Introduction.md)!

![Jira integration](doc/screenshot/issue_list_and_details.png)

Changes
-------

### v1.2.2

* FIX: Support for Icinga Web 2.11.0
* FIX: Some PHP 8.1 related issues

### v1.2.1

* FIX: Creating tickets in Jira now works again (#77)

### v1.2.0

* FEATURE: Support for PHP 8.1
* FEATURE: Support for Icinga DB
* FEATURE: Support for Icinga's dark and light mode
* FEATURE: Project dropdown is now sorted ()#73)

### v1.1.0

* FIX: Render status badge in case there is no related icon (#39)
* FIX: Use the same notification header via Web Form and CLI (#42)
* FIX: broken IssueDetails link has been fixed (#31, #48)
* FIX: we're sending Content-Length to make proxies happy (#51)
* FEATURE: Show status for created issues (#44)
* FEATURE: Allow choosing a default template (#36)
* FEATURE: Add configurable duedate for created Jira issues (#37)

### v1.0.1

* FIX: Ticket URLs pointing to Jira for Setups sitting in the DocumentRoot (#30)
* FIX: The new HTTP/HTTPS scheme setting didn't work (#30)
* FIX: There still was a dependency on Icinga Director (#28)
* FIX: Form for manually created issues didn't work without Icinga Director (#27)
