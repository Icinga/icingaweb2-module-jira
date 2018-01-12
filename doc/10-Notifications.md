Sending Notifications
=====================

This is what your monitoring software should call to send a notification to JIRA:

    icingacli jira send problem \
        --host some.example.com \
        --project ITSM \
        --issuetype Incident \
        --state DOWN \
        --description 'some.example.com is DOWN'
        --summary 'CRITICAL - 127.0.0.1: rta nan, lost 100%'

To get related documentation, the `--help` parameter could be useful. At the
time being, the output of `icingacli jira send problem --help` is as follows:

```
Create an issue for the given Host or Service problem
=====================================================

Use this as a NotificationCommand for Icinga

USAGE

icingacli jira send problem [options]

REQUIRED OPTIONS

  --project <project-name>     JIRA project name, like "ITSM"
  --issuetype <type-name>      JIRA issue type, like "Incident"
  --summary <summary>          JIRA issue summary
  --description <description>  JIRA issue description text
  --state <state-name>         Icinga state
  --host <host-name>           Icinga Host name

OPTIONAL

  --service <service-name>   Icinga Service name
  --template <template-name> Template name (templates.ini section)
  --ack-author <author>      Username shown for acknowledgements,
                             defaults to "JIRA"
  --command-pipe <path>      Legacy command pipe, allows to run without
                             depending on a configured monitoring module

FLAGS
  --verbose    More log information
  --trace      Get a full stack trace in case an error occurs
  --benchmark  Show timing and memory usage details
```

Icinga 2 NotificationCommand
----------------------------

In Icinga 2, a related `NotificationCommand` definition could look as follows:

```
object NotificationCommand "JIRA Host Notification" {
    import "plugin-notification-command"
    command = [ "/usr/bin/icingacli", "jira", "send", "problem" ]
    arguments += {
        "--ack-author" = {
            description = "This author name will be used when acknowledging Icinga problems once a JIRA issue got created"
            value = "$jira_ack_author$"
        }
        "--command-pipe" = {
            description = "Legacy Icinga command pipe. Should only be used on Icinga 1.x system without a correctly configured Icinga Web 2 monitoring module"
            value = "$jira_command_pipe$"
        }
        "--description" = {
            description = "JIRA issue description"
            required = true
            value = "$jira_description$"
        }
        "--host" = "$host.name$"
        "--issuetype" = {
            description = "JIRA issue type (e.g. Incident)"
            required = true
            value = "$jira_issuetype$"
        }
        "--project" = {
            description = "JIRA project name (e.g. ITSM)"
            required = true
            value = "$jira_project$"
        }
        "--state" = {
            description = "Host state (e.g. DOWN)"
            value = "$host.state$"
        }
        "--summary" = {
            description = "JIRA issue summary"
            required = true
            value = "$jira_summary$"
        }
        "--template" = {
            description = "Issue template name (templates.ini section). This allows to pass custom fields to JIRA"
            value = "$jira_template$"
        }
    }
    vars.jira_description = "$host.output$"
    vars.jira_summary = "$host.name$ is $host.state$"
}
```

Host- and Service-NotificationCommands need distinct command definitions, so here
is what it would look like for a Service:

```
object NotificationCommand "JIRA Service Notification" {
    import "plugin-notification-command"
    command = [ "/usr/bin/icingacli", "jira", "send", "problem" ]
    arguments += {
        "--ack-author" = {
            description = "This author name will be used when acknowledging Icinga problems once a JIRA issue got created"
            value = "$jira_ack_author$"
        }
        "--command-pipe" = {
            description = "Legacy Icinga command pipe. Should only be used on Icinga 1.x system without a correctly configured Icinga Web 2 monitoring module"
            value = "$jira_command_pipe$"
        }
        "--description" = {
            description = "JIRA issue description"
            required = true
            value = "$jira_description$"
        }
        "--host" = "$host.name$"
        "--issuetype" = {
            description = "JIRA issue type (e.g. Incident)"
            required = true
            value = "$jira_issuetype$"
        }
        "--project" = {
            description = "JIRA project name (e.g. ITSM)"
            required = true
            value = "$jira_project$"
        }
        "--service" = "$service.name$"
        "--state" = {
            description = "Service state (e.g. CRITICAL)"
            value = "$service.state$"
        }
        "--summary" = {
            description = "JIRA issue summary"
            required = true
            value = "$jira_summary$"
        }
        "--template" = {
            description = "Issue template name (templates.ini section). This allows to pass custom fields to JIRA"
            value = "$jira_template$"
        }
    }
    vars.jira_description = "$service.output$"
    vars.jira_summary = "$service.name$ on $host.name$ is $service.state$"
}
```

In case you're running the [Icinga Director](https://github.com/Icinga/icingaweb2-module-director)
(you really should), then there is no need to configure this manually. Please
head on to our [Director Integration](12-Director-Integration.md) section.

Icinga 1 command
----------------

Still running Icinga 1.x? You should definitively migrate to Icinga 2! However,
Icinga Web 2.x plays nicely with Icinga 1 - and so does this module. This is how
your command definitions could look like:

```
define command {
    command_name    jira-notify-host
    command_line    /usr/bin/icingacli jira send problem \
        --project 'ITSM' \
        --issuetype 'Incident' \
        --summary '$HOSTNAME$ is $HOSTSTATE$' \
        --description '$HOSTOUTPUT$' \
        --host '$HOSTNAME$' \
        --state $HOSTSTATE$
}

define command {
    command_name    jira-notify-service
    command_line    /usr/bin/icingacli jira send problem \
        --project 'ITSM' \
        --issuetype 'Incident' \
        --summary '$SERVICEDESC$ on $HOSTNAME$ is $SERVICESTATE$' \
        --description '$HOSTOUTPUT$' \
        --host '$HOSTNAME$' \
        --service '$SERVICEDESC$' \
        --state $SERVICESTATE$
}
```
