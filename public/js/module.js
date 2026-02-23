// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

(function (Icinga) {
    var Jira = function(module) {
        this.module = module;
        this.initialize();
        this.module.icinga.logger.debug('Jira module loaded');
    };

    Jira.prototype = {
        initialize: function () {
            this.module.on('rendered', this.onRendered);
        },
        
        onRendered: function (event) {
            var $container = $(event.currentTarget);
            // Not yet
        }
    };
    Icinga.availableModules.jira = Jira;

}(Icinga));
