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

package log

import (
	"time"
	"unsafe"
	"zabbix/internal/plugin"
	"zabbix/pkg/zbxlib"
)

type logTask struct {
	clientid  uint64
	output    plugin.ResultWriter
	scheduled time.Time
	updated   time.Time
	lastCheck time.Time
	active    bool
	index     int
	itemid    uint64
	key       string
	delay     string
	failed    bool
	data      unsafe.Pointer
}

func (t *logTask) deactivate() {
	t.active = false
	zbxlib.FreeActiveMetric(t.data)
}

func (t *logTask) perform(key string, p *Plugin, now time.Time) {
	var refresh int
	if t.lastCheck.IsZero() {
		refresh = 1
	} else {
		refresh = int((now.Sub(t.lastCheck) + time.Second/2) / time.Second)
	}
	// with flexible checks there are no guaranteed refresh time,
	// so using number of seconds elapsed since last check
	zbxlib.ProcessLogCheck(t.data, &zbxlib.LogItem{Itemid: t.itemid, Output: t.output}, refresh)

	t.lastCheck = now
	p.input <- t
}

func (t *logTask) reschedule(now time.Time) {
	// TODO: refresh unsupported support
	var err error
	if t.scheduled, err = zbxlib.GetNextcheck(t.itemid, t.delay, now, t.failed, 60); err != nil {
		t.failed = true
		t.output.Write(&plugin.Result{Itemid: t.itemid, Error: err, Ts: now})
		t.scheduled, _ = zbxlib.GetNextcheck(t.itemid, "0", now, t.failed, 60)
	}
}
