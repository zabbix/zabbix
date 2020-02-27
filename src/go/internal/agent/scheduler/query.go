/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package scheduler

import (
	"errors"
	"fmt"
	"sort"
	"strings"

	"zabbix.com/pkg/plugin"
)

type pluginMetrics struct {
	ref     *pluginAgent
	metrics []*plugin.Metric
}

func (m *Manager) getStatus() (result string) {
	var status strings.Builder
	agents := make(map[plugin.Accessor]*pluginMetrics)
	infos := make([]*pluginMetrics, 0, len(m.plugins))
	for _, p := range m.plugins {
		if _, ok := agents[p.impl]; !ok {
			info := &pluginMetrics{ref: p, metrics: make([]*plugin.Metric, 0)}
			infos = append(infos, info)
			agents[p.impl] = info
		}
	}

	for _, metric := range plugin.Metrics {
		if info, ok := agents[metric.Plugin]; ok {
			info.metrics = append(info.metrics, metric)
		}
	}
	sort.Slice(infos, func(i, j int) bool {
		return infos[i].ref.name() < infos[j].ref.name()
	})

	for _, info := range infos {
		status.WriteString(fmt.Sprintf("[%s]\nactive: %t\ncapacity: %d/%d\ntasks: %d\n",
			info.ref.name(), info.ref.active(), info.ref.usedCapacity, info.ref.capacity, len(info.ref.tasks)))
		sort.Slice(info.metrics, func(l, r int) bool { return info.metrics[l].Key < info.metrics[r].Key })
		for _, metric := range info.metrics {
			status.WriteString(metric.Key)
			status.WriteString(": ")
			status.WriteString(metric.Description)
			status.WriteString("\n")
		}
		status.WriteString("\n")
	}
	return status.String()
}

func (m *Manager) processQuery(r *queryRequest) (text string, err error) {
	switch r.command {
	case "metrics":
		return m.getStatus(), nil
	default:
		return "", errors.New("unknown request")
	}
}
