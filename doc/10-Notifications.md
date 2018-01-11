Sending Notifications
=====================

This is what your monitoring software should call to send a notification to JIRA:

    icingacli jira send problem \
        --host some.example.com \
        --state DOWN \
        --project ITSM \
        --issuetype Incident \
        --output 'CRITICAL - 127.0.0.1: rta nan, lost 100%'

Icinga 2 NotificationCommand
----------------------------

In Icinga 2, a related `NotificationCommand` definition could look as follows:

```
object NotificationCommand "JIRA Host Notification" {
    import "plugin-notification-command"
    command = [ "/usr/bin/icingacli", "jira", "send", "problem" ]
    arguments += {
        "--host" = "$host.name$"
        "--host-alias" = "$host.display_name$"
        "--issuetype" = {
            required = true
            value = "$jira_issuetype$"
        }
        "--output" = "$host.output$"
        "--project" = {
            required = true
            value = "$jira_project$"
        }
        "--state" = "$host.state$"
    }
}
```

Host- and Service-NotificationCommands need distinct command definitions, so here
is what it would look like for a Service:

```
object NotificationCommand "JIRA Service Notification" {
    import "plugin-notification-command"
    command = [ "/usr/bin/icingacli", "jira", "send", "problem" ]
    arguments += {
        "--host" = "$host.name$"
        "--host-alias" = "$host.display_name$"
        "--issuetype" = {
            required = true
            value = "$jira_issuetype$"
        }
        "--output" = "$service.output$"
        "--project" = {
            required = true
            value = "$jira_project$"
        }
        "--service" = "$service.name$"
        "--state" = "$service.state$"
    }
}
```

In case you're running the [Icinga Director](https://github.com/Icinga/icingaweb2-module-director)
(you really should), then there is no need to configure this manually. Please
head on to our [Director Integration](12-Director-Integration.md) section.

Icinga 1 command
----------------

Still running Icinga 1.x? You should definitively migrate to Icinga 2! However,
Icinga Web 2.x plays nicely with Icinga 1 - and so does this module. This is how
your command definition could look like:

```
define command {
    command_name    jira-notify-host
    command_line    /usr/bin/icingacli jira send problem --host '$HOSTADDRESS$' \
        --host-alias '$HOSTALIAS$' --issuetype 'Incident' --project ITSM \
        --state $HOSTSTATE$ --output '$HOSTOUTPUT$'
}
```
