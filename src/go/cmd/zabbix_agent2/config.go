/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package main

import (
	"fmt"
	"strings"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
	"golang.zabbix.com/agent2/pkg/zbxcmd"
	"golang.zabbix.com/sdk/log"
)

func updateHostname(taskManager scheduler.Scheduler, options *agent.AgentOptions) error {
	var maxLen int
	var err error

	if len(options.Hostname) == 0 {
		var hostnameItem string
		var taskResult *string

		if len(options.HostnameItem) == 0 {
			hostnameItem = "system.hostname"
		} else {
			hostnameItem = options.HostnameItem
		}

		taskResult, err = taskManager.PerformTask(hostnameItem, time.Second*time.Duration(options.Timeout), agent.LocalChecksClientID)
		if err != nil {
			if len(options.HostnameItem) == 0 {
				return fmt.Errorf("cannot get system hostname using \"%s\" item as default for \"HostnameItem\" configuration parameter: %s", hostnameItem, err.Error())
			}

			return fmt.Errorf("cannot get system hostname using \"%s\" item specified by \"HostnameItem\" configuration parameter: %s", hostnameItem, err.Error())
		}

		if taskResult == nil || len(*taskResult) == 0 {
			return fmt.Errorf("cannot get system hostname using \"%s\" item specified by \"HostnameItem\" configuration parameter: value is empty", hostnameItem)
		}

		options.Hostname = *taskResult
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
