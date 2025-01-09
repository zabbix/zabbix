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
package resultcache

import (
	"encoding/json"
	"errors"
	"reflect"
	"testing"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

type mockWriter struct {
	counter int
	lastid  uint64
	t       *testing.T
}

func (w *mockWriter) Write(data []byte, timeout time.Duration) (upload bool, err []error) {
	log.Debugf("%s", string(data))
	if w.counter&1 != 0 {
		err = []error{errors.New("mock error")}
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
	upload = true
	w.counter++
	return
}

func (w *mockWriter) Addr() string {
	return ""
}

func (w *mockWriter) CanRetry() bool {
	return false
}

func (w *mockWriter) Hostname() string {
	return ""
}

func (w *mockWriter) Session() string {
	return ""
}

func TestResultCache(t *testing.T) {
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	_ = log.Open(log.Console, log.Debug, "", 0)

	writer := mockWriter{lastid: 1, t: t}
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)

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

func checkBuffer(t *testing.T, c *MemoryCache, input []*plugin.Result, expected []*AgentData) {
	for _, r := range input {
		c.write(r)
	}

	if !reflect.DeepEqual(c.results, expected) {
		t.Errorf("Expected:")
		for _, d := range expected {
			t.Errorf("    %+v", *d)
		}
		t.Errorf("While got:")
		for _, d := range c.results {
			t.Errorf("    %+v", *d)
		}
	}
}

func TestBuffer(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
	}

	expected := []*AgentData{
		{Id: 1, Itemid: 1},
		{Id: 2, Itemid: 2},
		{Id: 3, Itemid: 3},
		{Id: 4, Itemid: 4},
		{Id: 5, Itemid: 5},
		{Id: 6, Itemid: 6},
		{Id: 7, Itemid: 7},
		{Id: 8, Itemid: 8},
		{Id: 9, Itemid: 9},
		{Id: 10, Itemid: 10},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
		{Itemid: 11},
		{Itemid: 12},
		{Itemid: 13},
		{Itemid: 14},
		{Itemid: 15},
	}

	expected := []*AgentData{
		{Id: 6, Itemid: 6},
		{Id: 7, Itemid: 7},
		{Id: 8, Itemid: 8},
		{Id: 9, Itemid: 9},
		{Id: 10, Itemid: 10},
		{Id: 11, Itemid: 11},
		{Id: 12, Itemid: 12},
		{Id: 13, Itemid: 13},
		{Id: 14, Itemid: 14},
		{Id: 15, Itemid: 15},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5ReplaceFirst(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
		{Itemid: 1},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
	}

	expected := []*AgentData{
		{Id: 6, Itemid: 6},
		{Id: 7, Itemid: 7},
		{Id: 8, Itemid: 8},
		{Id: 9, Itemid: 9},
		{Id: 10, Itemid: 10},
		{Id: 11, Itemid: 1},
		{Id: 12, Itemid: 2},
		{Id: 13, Itemid: 3},
		{Id: 14, Itemid: 4},
		{Id: 15, Itemid: 5},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5ReplaceLast(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
	}

	expected := []*AgentData{
		{Id: 1, Itemid: 1},
		{Id: 2, Itemid: 2},
		{Id: 3, Itemid: 3},
		{Id: 4, Itemid: 4},
		{Id: 5, Itemid: 5},
		{Id: 11, Itemid: 6},
		{Id: 12, Itemid: 7},
		{Id: 13, Itemid: 8},
		{Id: 14, Itemid: 9},
		{Id: 15, Itemid: 10},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5ReplacInterleaved(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
		{Itemid: 1},
		{Itemid: 3},
		{Itemid: 5},
		{Itemid: 7},
		{Itemid: 9},
	}

	expected := []*AgentData{
		{Id: 2, Itemid: 2},
		{Id: 4, Itemid: 4},
		{Id: 6, Itemid: 6},
		{Id: 8, Itemid: 8},
		{Id: 10, Itemid: 10},
		{Id: 11, Itemid: 1},
		{Id: 12, Itemid: 3},
		{Id: 13, Itemid: 5},
		{Id: 14, Itemid: 7},
		{Id: 15, Itemid: 9},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5OneItem(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
		{Itemid: 1},
		{Itemid: 1},
		{Itemid: 1},
		{Itemid: 1},
		{Itemid: 1},
	}

	expected := []*AgentData{
		{Id: 2, Itemid: 2},
		{Id: 3, Itemid: 3},
		{Id: 4, Itemid: 4},
		{Id: 5, Itemid: 5},
		{Id: 6, Itemid: 6},
		{Id: 7, Itemid: 7},
		{Id: 8, Itemid: 8},
		{Id: 9, Itemid: 9},
		{Id: 10, Itemid: 10},
		{Id: 15, Itemid: 1},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull4OneItemPersistent(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1, Persistent: true},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
	}

	expected := []*AgentData{
		{Id: 1, Itemid: 1, persistent: true},
		{Id: 6, Itemid: 6},
		{Id: 7, Itemid: 7},
		{Id: 8, Itemid: 8},
		{Id: 9, Itemid: 9},
		{Id: 10, Itemid: 10},
		{Id: 11, Itemid: 1, persistent: true},
		{Id: 12, Itemid: 1, persistent: true},
		{Id: 13, Itemid: 1, persistent: true},
		{Id: 14, Itemid: 1, persistent: true},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull5OneItemPersistent(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1, Persistent: true},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
	}

	expected := []*AgentData{
		{Id: 1, Itemid: 1, persistent: true},
		{Id: 6, Itemid: 6},
		{Id: 7, Itemid: 7},
		{Id: 8, Itemid: 8},
		{Id: 9, Itemid: 9},
		{Id: 10, Itemid: 10},
		{Id: 11, Itemid: 1, persistent: true},
		{Id: 12, Itemid: 1, persistent: true},
		{Id: 13, Itemid: 1, persistent: true},
		{Id: 14, Itemid: 1, persistent: true},
		{Id: 15, Itemid: 1, persistent: true},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}

func TestBufferFull10PersistentAndNormal(t *testing.T) {
	input := []*plugin.Result{
		{Itemid: 1, Persistent: true},
		{Itemid: 2},
		{Itemid: 3},
		{Itemid: 4},
		{Itemid: 5},
		{Itemid: 6},
		{Itemid: 7},
		{Itemid: 8},
		{Itemid: 9},
		{Itemid: 10},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 1, Persistent: true},
		{Itemid: 6},
		{Itemid: 6},
		{Itemid: 11},
		{Itemid: 12},
		{Itemid: 13},
	}

	expected := []*AgentData{
		{Id: 1, Itemid: 1, persistent: true},
		{Id: 10, Itemid: 10},
		{Id: 11, Itemid: 1, persistent: true},
		{Id: 12, Itemid: 1, persistent: true},
		{Id: 13, Itemid: 1, persistent: true},
		{Id: 14, Itemid: 1, persistent: true},
		{Id: 15, Itemid: 1, persistent: true},
		{Id: 17, Itemid: 6},
		{Id: 18, Itemid: 11},
		{Id: 19, Itemid: 12},
		{Id: 20, Itemid: 13},
	}

	_ = log.Open(log.Console, log.Debug, "", 0)
	agent.Options.BufferSize = 10
	agent.Options.EnablePersistentBuffer = 0
	c := New(&agent.Options, 0, nil)
	cache := c.(*MemoryCache)
	checkBuffer(t, cache, input, expected)
}
