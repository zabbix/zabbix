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
package resultcache

import (
	"encoding/json"
	"errors"
	"reflect"
	"testing"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/log"
	"zabbix.com/pkg/plugin"
)

type mockWriter struct {
	counter int
	lastid  uint64
	t       *testing.T
}

func (w *mockWriter) Write(data []byte, timeout time.Duration) (err error) {
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

func (w *mockWriter) Addr() string {
	return ""
}

func (w *mockWriter) CanRetry() bool {
	return false
}

func TestResultCache(t *testing.T) {
	agent.Options.BufferSize = 10
	_ = log.Open(log.Console, log.Debug, "", 0)

	writer := mockWriter{lastid: 1, t: t}
	cache := NewActive(0, nil)

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

func checkBuffer(t *testing.T, rc *ResultCache, input []*plugin.Result, expected []*AgentData) {
	for _, r := range input {
		rc.write(r)
	}

	if !reflect.DeepEqual(rc.results, expected) {
		t.Errorf("Expected:")
		for _, d := range expected {
			t.Errorf("    %+v", *d)
		}
		t.Errorf("While got:")
		for _, d := range rc.results {
			t.Errorf("    %+v", *d)
		}
	}
}

func TestBuffer(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
	}

	expected := []*AgentData{
		&AgentData{Id: 1, Itemid: 1},
		&AgentData{Id: 2, Itemid: 2},
		&AgentData{Id: 3, Itemid: 3},
		&AgentData{Id: 4, Itemid: 4},
		&AgentData{Id: 5, Itemid: 5},
		&AgentData{Id: 6, Itemid: 6},
		&AgentData{Id: 7, Itemid: 7},
		&AgentData{Id: 8, Itemid: 8},
		&AgentData{Id: 9, Itemid: 9},
		&AgentData{Id: 10, Itemid: 10},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
		&plugin.Result{Itemid: 11},
		&plugin.Result{Itemid: 12},
		&plugin.Result{Itemid: 13},
		&plugin.Result{Itemid: 14},
		&plugin.Result{Itemid: 15},
	}

	expected := []*AgentData{
		&AgentData{Id: 6, Itemid: 6},
		&AgentData{Id: 7, Itemid: 7},
		&AgentData{Id: 8, Itemid: 8},
		&AgentData{Id: 9, Itemid: 9},
		&AgentData{Id: 10, Itemid: 10},
		&AgentData{Id: 11, Itemid: 11},
		&AgentData{Id: 12, Itemid: 12},
		&AgentData{Id: 13, Itemid: 13},
		&AgentData{Id: 14, Itemid: 14},
		&AgentData{Id: 15, Itemid: 15},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5ReplaceFirst(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
	}

	expected := []*AgentData{
		&AgentData{Id: 6, Itemid: 6},
		&AgentData{Id: 7, Itemid: 7},
		&AgentData{Id: 8, Itemid: 8},
		&AgentData{Id: 9, Itemid: 9},
		&AgentData{Id: 10, Itemid: 10},
		&AgentData{Id: 11, Itemid: 1},
		&AgentData{Id: 12, Itemid: 2},
		&AgentData{Id: 13, Itemid: 3},
		&AgentData{Id: 14, Itemid: 4},
		&AgentData{Id: 15, Itemid: 5},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5ReplaceLast(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
	}

	expected := []*AgentData{
		&AgentData{Id: 1, Itemid: 1},
		&AgentData{Id: 2, Itemid: 2},
		&AgentData{Id: 3, Itemid: 3},
		&AgentData{Id: 4, Itemid: 4},
		&AgentData{Id: 5, Itemid: 5},
		&AgentData{Id: 11, Itemid: 6},
		&AgentData{Id: 12, Itemid: 7},
		&AgentData{Id: 13, Itemid: 8},
		&AgentData{Id: 14, Itemid: 9},
		&AgentData{Id: 15, Itemid: 10},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5ReplacInterleaved(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 9},
	}

	expected := []*AgentData{
		&AgentData{Id: 2, Itemid: 2},
		&AgentData{Id: 4, Itemid: 4},
		&AgentData{Id: 6, Itemid: 6},
		&AgentData{Id: 8, Itemid: 8},
		&AgentData{Id: 10, Itemid: 10},
		&AgentData{Id: 11, Itemid: 1},
		&AgentData{Id: 12, Itemid: 3},
		&AgentData{Id: 13, Itemid: 5},
		&AgentData{Id: 14, Itemid: 7},
		&AgentData{Id: 15, Itemid: 9},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5OneItem(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 1},
		&plugin.Result{Itemid: 1},
	}

	expected := []*AgentData{
		&AgentData{Id: 2, Itemid: 2},
		&AgentData{Id: 3, Itemid: 3},
		&AgentData{Id: 4, Itemid: 4},
		&AgentData{Id: 5, Itemid: 5},
		&AgentData{Id: 6, Itemid: 6},
		&AgentData{Id: 7, Itemid: 7},
		&AgentData{Id: 8, Itemid: 8},
		&AgentData{Id: 9, Itemid: 9},
		&AgentData{Id: 10, Itemid: 10},
		&AgentData{Id: 15, Itemid: 1},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull4OneItemPersistent(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
	}

	expected := []*AgentData{
		&AgentData{Id: 1, Itemid: 1, persistent: true},
		&AgentData{Id: 6, Itemid: 6},
		&AgentData{Id: 7, Itemid: 7},
		&AgentData{Id: 8, Itemid: 8},
		&AgentData{Id: 9, Itemid: 9},
		&AgentData{Id: 10, Itemid: 10},
		&AgentData{Id: 11, Itemid: 1, persistent: true},
		&AgentData{Id: 12, Itemid: 1, persistent: true},
		&AgentData{Id: 13, Itemid: 1, persistent: true},
		&AgentData{Id: 14, Itemid: 1, persistent: true},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5OneItemPersistent(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
	}

	expected := []*AgentData{
		&AgentData{Id: 1, Itemid: 1, persistent: true},
		&AgentData{Id: 6, Itemid: 6},
		&AgentData{Id: 7, Itemid: 7},
		&AgentData{Id: 8, Itemid: 8},
		&AgentData{Id: 9, Itemid: 9},
		&AgentData{Id: 10, Itemid: 10},
		&AgentData{Id: 11, Itemid: 1, persistent: true},
		&AgentData{Id: 12, Itemid: 1, persistent: true},
		&AgentData{Id: 13, Itemid: 1, persistent: true},
		&AgentData{Id: 14, Itemid: 1, persistent: true},
		&AgentData{Id: 15, Itemid: 1, persistent: true},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull10PersistentAndNormal(t *testing.T) {
	input := []*plugin.Result{
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 2},
		&plugin.Result{Itemid: 3},
		&plugin.Result{Itemid: 4},
		&plugin.Result{Itemid: 5},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 7},
		&plugin.Result{Itemid: 8},
		&plugin.Result{Itemid: 9},
		&plugin.Result{Itemid: 10},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 1, Persistent: true},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 6},
		&plugin.Result{Itemid: 11},
		&plugin.Result{Itemid: 12},
		&plugin.Result{Itemid: 13},
	}

	expected := []*AgentData{
		&AgentData{Id: 1, Itemid: 1, persistent: true},
		&AgentData{Id: 10, Itemid: 10},
		&AgentData{Id: 11, Itemid: 1, persistent: true},
		&AgentData{Id: 12, Itemid: 1, persistent: true},
		&AgentData{Id: 13, Itemid: 1, persistent: true},
		&AgentData{Id: 14, Itemid: 1, persistent: true},
		&AgentData{Id: 15, Itemid: 1, persistent: true},
		&AgentData{Id: 17, Itemid: 6},
		&AgentData{Id: 18, Itemid: 11},
		&AgentData{Id: 19, Itemid: 12},
		&AgentData{Id: 20, Itemid: 13},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	cache := NewActive(0, nil)
	checkBuffer(t, cache, input, expected)
}
