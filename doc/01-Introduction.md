Introduction
============

This module allows to send notifications to JIRA and integrates nicely into the
Icinga Web 2 frontend.

Features
--------

### NotificationCommand for Icinga

You want to create a JIRA issue for all or some of your Hosts and Services?
Then this project might be what you're looking for. Sending notifications is
as easy as follows:

    icingacli jira send problem \
      --host some.example.com \
      --state DOWN \
      --project ITSM \
      --issuetype Incident \
      --output 'CRITICAL - 127.0.0.1: rta nan, lost 100%'

Read more about [sending notifications](doc/10-Notifications.md).

### Icinga Director Integration

You do not want to manually create a `NotificationCommand` definition? No need
to do so. Given `director/admin` permissions, this module allows you to generate
definitions for hosts and services with a single click.

![Icinga Director Integration](doc/screenshot/director_preview.png)

Have a look at out [short tutorial](doc/12-Director-Integration.md), showing how
this works.

### Brief issue history overview

Granted access to this module you can get a quick overview showing the most recent
JIRA issues created by Icinga. Read more about [what information](doc/20-Issue-History.md)
we're going to show.

![Issue list and details](doc/screenshot/issue_list_and_details_small.png)

### Monitoring module hook

This module hooks into the `monitoring` module and provides so-called Host and
Service Action Hooks:

![Monitoring Action Hook](doc/screenshot/monitoring_action_hook.png)

A single click brings you to your Host (or Service) issue history.

Getting started
---------------

It's free and Open Source, so what are you waiting for? Please read on how to
[install and configure](doc/03-Configuration.md) this module.
