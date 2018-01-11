Installation and Configuration
==============================

Dependencies
------------

* Icinga Web 2 (&gt;= 2.4.1)
* Icinga Director (&gt;= v1.4.0)
* PHP (&gt;= 5.4 or 7.x)

The Icinga Web 2 `monitoring` module should be configured and enabled.

Even if not using [Icinga Director](https://github.com/Icinga/icingaweb2-module-director),
it must at least be installed and enabled in your [Icinga Web 2](https://github.com/Icinga/icingaweb2).
This module borrows some libraries from the Director.

Installation from .tar.gz
-------------------------

Download the latest version and extract it to a folder named `jira`
in one of your Icinga Web 2 module path directories.

You might want to use a script as follows for this task:
```sh
ICINGAWEB_MODULEPATH="/usr/share/icingaweb2/modules"
REPO_URL="https://github.com/Icinga/icingaweb2-module-jira"
TARGET_DIR="${ICINGAWEB_MODULEPATH}/jira"
MODULE_VERSION="1.0.0"
URL="${REPO_URL}/archive/v${MODULE_VERSION}.tar.gz"
install -d -m 0755 "${TARGET_DIR}"
wget -q -O - "$URL" | tar xfz - -C "${TARGET_DIR}" --strip-components 1
```

Installation from GIT repository
--------------------------------

Another convenient method is the installation directly from our GIT repository.
Just clone the repository to one of your Icinga Web 2 module path directories.
It will be immediately ready for use:

```sh
ICINGAWEB_MODULEPATH="/usr/share/icingaweb2/modules"
REPO_URL="https://github.com/Icinga/icingaweb2-module-jira"
TARGET_DIR="${ICINGAWEB_MODULEPATH}/jira"
MODULE_VERSION="1.0.0"
git clone "${REPO_URL}" "${TARGET_DIR}"
git checkout "v${MODULE_VERSION}"
```

You can now directly use our current GIT master or check out a specific version.

Enable the newly installed module
---------------------------------

Enable the `jira` module either on the CLI by running

```sh
icingacli module enable jira
```

Or go to your Icinga Web 2 frontend, choose `Configuration` -&gt; `Modules`...

![Configuration - Modules](screenshot/menu_configuration_modules.png)

select the `jira` module and `enable` it:

![Jira module details](screenshot/configuration_module_details.png)


Configuration
-------------

Currently you have to manually create a related configuration file. In future
we'd love to allow you to provide these settings directly in the Web GUI. For now
please create a dedicated module configuration directory, like:

    install -d -m 2770 -o www-data -g icingaweb2 /etc/icingaweb2/modules/jira

Please adjust owner and group to fit your system, and also the directory in case
your `ICINGAWEB_CONFIGDIR` is not `/etc/icingaweb2`.

We need a new file named `config.ini` in this newly created directory:

```ini
[api]
host = "jira.example.com"
; port = 443
; path = "/"
username = "icinga"
password = "***"
```

The `port` and `path` settings are optional, protocol is always HTTPS. The
given user needs permissions to create (and show) issues in at least one JIRA
project.

If you want to run `icingacli` commands (read: send notifications), then your
Icinga user must be member of the `icingaweb2` group. In case it isn't, this
can usually be fixed as follows:

    usermod -a -G icingaweb2 icinga2

Depending on your OS configuration, it might be required to restart Icinga 2
afterwards:

    systemctl restart icinga2.service

That's it, now you should be ready to start [Sending Notifications](10-Notifications.md).
