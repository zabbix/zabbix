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
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/internal/agent/scheduler"
)

func checkMetric(s scheduler.Scheduler, metric string) {
	const timeoutForTestrunChecks = time.Minute

	value, err := s.PerformTask(metric, timeoutForTestrunChecks, agent.TestrunClientID)
	if err != nil {
		fmt.Printf("%-46s[m|ZBX_NOTSUPPORTED] [%s]\n", metric, err.Error())
	} else if value == nil {
		fmt.Printf("%-46s[-|ZBX_NODATA]\n", metric)
	} else {
		fmt.Printf("%-46s[s|%s]\n", metric, *value)
	}
}
