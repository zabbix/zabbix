/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package main

import (
	"fmt"
	"strings"
	"time"

	"git.zabbix.com/ap/plugin-support/log"
	"zabbix.com/internal/agent"
	"zabbix.com/internal/agent/scheduler"
	"zabbix.com/pkg/zbxcmd"
)

func updateHostname(taskManager scheduler.Scheduler, options *agent.AgentOptions) error {
	var maxLen int
	var err error

	if len(options.Hostname) == 0 {
		var hostnameItem string

		if len(options.HostnameItem) == 0 {
			hostnameItem = "system.hostname"
		} else {
			hostnameItem = options.HostnameItem
		}

		options.Hostname, err = taskManager.PerformTask(hostnameItem, time.Second*time.Duration(options.Timeout), agent.LocalChecksClientID)
		if err != nil {
			if len(options.HostnameItem) == 0 {
				return fmt.Errorf("cannot get system hostname using \"%s\" item as default for \"HostnameItem\" configuration parameter: %s", hostnameItem, err.Error())
			}

			return fmt.Errorf("cannot get system hostname using \"%s\" item specified by \"HostnameItem\" configuration parameter: %s", hostnameItem, err.Error())
		}
		if len(options.Hostname) == 0 {
			return fmt.Errorf("cannot get system hostname using \"%s\" item specified by \"HostnameItem\" configuration parameter: value is empty", hostnameItem)
		}
		hosts := agent.ExtractHostnames(options.Hostname)
		options.Hostname = strings.Join(hosts, ",")
		if len(hosts) > 1 {
			maxLen = zbxcmd.MaxExecuteOutputLenB
		} else {
			maxLen = agent.HostNameLen
		}
		if len(options.Hostname) > maxLen {
			options.Hostname = options.Hostname[:maxLen]
			log.Warningf("the returned value of \"%s\" item specified by \"HostnameItem\" configuration parameter is too long, using first %d characters", hostnameItem, maxLen)
		}

		if err = agent.CheckHostnameParameter(options.Hostname); err != nil {
			return fmt.Errorf("cannot get system hostname using \"%s\" item specified by \"HostnameItem\" configuration parameter: %s", hostnameItem, err.Error())
		}
	} else {
		if len(options.HostnameItem) != 0 {
			log.Warningf("both \"Hostname\" and \"HostnameItem\" configuration parameter defined, using \"Hostname\"")
		}
	}

	return nil
}

func configUpdateItemParameters(taskManager scheduler.Scheduler, options *agent.AgentOptions) error {
	var err error

	if err = updateHostname(taskManager, options); err != nil {
		return err
	}

	return nil
}
