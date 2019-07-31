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
package resultcache

import (
	"encoding/json"
	"errors"
	"testing"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/log"
)

type mockWriter struct {
	counter int
	lastid  uint64
	t       *testing.T
}

func (w *mockWriter) Write(data []byte) (n int, err error) {
	log.Debugf("%s", string(data))
	if w.counter&1 != 0 {
		err = errors.New("mock error")
	} else {
		var request AgentDataRequest
		_ = json.Unmarshal(data, &request)
		for _, d := range request.Data {
			if d.Id != w.lastid {
				w.t.Errorf("Expected %d data id while got %d", w.lastid, d.Id)
				w.t.Fail()
			}
			w.lastid++
		}
	}

	w.counter++
	return
}

func TestResultCache(t *testing.T) {
	_ = log.Open(log.Console, log.Debug, "")

	writer := mockWriter{lastid: 1, t: t}
	cache := NewActive(&writer)

	value := "xyz"
	result := plugin.Result{
		Itemid: 1,
		Value:  &value,
		Ts:     time.Now(),
	}

	cache.write(&result)
	cache.flushOutput(&writer)

	cache.write(&result)
	cache.write(&result)
	cache.flushOutput(&writer)

	cache.write(&result)
	cache.write(&result)
	cache.write(&result)
	cache.write(&result)
	cache.flushOutput(&writer)
}

func TestToken(t *testing.T) {
	tokens := make(map[string]bool)
	for i := 0; i < 100000; i++ {
		token := newToken()
		if len(token) != 32 {
			t.Errorf("Expected token length 32 while got %d", len(token))
			return
		}
		if _, ok := tokens[token]; ok {
			t.Errorf("Duplicated token detected")
		}
		tokens[token] = true
	}
}
