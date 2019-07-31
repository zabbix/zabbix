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
	"zabbix/internal/plugin"
	"zabbix/pkg/log"
)

type mockWriter struct {
}

func (w *mockWriter) Write(data []byte) (n int, err error) {
	log.Debugf("WRITE: %s", string(data))
	return len(data), nil
}

func TestResultCache(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	var writer mockWriter
	cache := NewActiveCache(&writer)
	cache.Start()

	value := "xyz"
	result := plugin.Result{
		Itemid: 1,
		Value:  &value,
		Ts:     time.Now(),
	}

	cache.Write(&result)
	cache.FlushOutput(&writer)

	cache.Write(&result)
	cache.Write(&result)
	cache.Flush()

	time.Sleep(time.Second)
	cache.Stop()

}
