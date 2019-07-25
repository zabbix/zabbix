/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package agent

import (
	"testing"
	"time"
	"zabbix/internal/monitor"
	"zabbix/internal/plugin"
	"zabbix/pkg/log"
)

type ResultCacheMock struct {
}

func (d *ResultCacheMock) Write(r *plugin.Result) {
	if r.Value != nil {
		log.Debugf("OK: %s\n", *r.Value)
	} else {
		log.Debugf("ERROR: %s\n", r.Error.Error())
	}
}

type DebugPlugin struct {
	plugin.Base
}

func (p *DebugPlugin) Export(key string, params []string) (result interface{}, err error) {
	return params[0] + ": " + time.Now().Format(time.RFC3339), nil
}

func TestParseKey(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	var debug DebugPlugin
	plugin.RegisterMetric(&debug, "debug", "debug", "Debug")

	var scheduler Scheduler
	scheduler.Start()
	var resultCache ResultCacheMock

	requests := make([]*plugin.Request, 0)
	requests = append(requests, &plugin.Request{
		Itemid: 1,
		Key:    "debug[1]",
		Delay:  "1",
	})
	/*
		requests = append(requests, &plugin.Request{
			Itemid: 2,
			Key:    "debug[2]",
			Delay:  "2",
		})
	*/
	scheduler.Update(&resultCache, requests)
	time.Sleep(time.Second * 5)
	requests[0].Delay = "2"
	scheduler.Update(&resultCache, requests)
	time.Sleep(time.Second * 5)

	scheduler.Stop()
	monitor.Wait()
}
