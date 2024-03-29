Introduction
============

This module allows to send notifications to Jira and integrates nicely into the
Icinga Web 2 frontend.

Features
--------

### NotificationCommand for Icinga

You want to create a Jira issue for all or some of your Hosts and Services?
Then this project might be what you're looking for. Sending notifications is
as easy as follows:

    icingacli jira send problem \
      --host some.example.com \
      --state DOWN \
      --project ITSM \
      --issuetype Incident \
      --output 'CRITICAL - 127.0.0.1: rta nan, lost 100%'

Read more about [sending notifications](10-Notifications.md).

### Icinga Director Integration

You do not want to manually create a `NotificationCommand` definition? No need
to do so. Given `director/admin` permissions, this module allows you to generate
definitions for hosts and services with a single click.

![Icinga Director Integration](screenshot/director_preview.png)

Have a look at our [short tutorial](12-Director-Integration.md), showing how
this works.

### Brief issue history overview

Granted access to this module you can get a quick overview showing the most recent
Jira issues created by Icinga. Read more about [what information](20-Issue-History.md)
we're going to show.

![Issue list and details](screenshot/issue_list_and_details.png)

### Monitoring / Icinga DB Web integration

This module hooks into the `monitoring` or `icingadb` module and provides so-called Host and
Service Action Hooks:

![Monitoring Action Hook](screenshot/host_action_hook.png)

A single click brings you to your Host (or Service) issue history.

Getting started
---------------

Please read on how to [install and configure](03-Configuration.md) this module.
