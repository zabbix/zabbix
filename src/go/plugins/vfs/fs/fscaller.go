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

package vfsfs

import (
	"fmt"
	"sync"
	"time"
)

const timeout = 1

var stuckMounts map[string]int
var stuckMux sync.Mutex

type fsCaller struct {
	fsFunc                func(path string) (stats *FsStats, err error)
	errChan               chan error
	outChanStuckUnchecked chan *FsStats
	outChanStuckChecked   chan interface{}
	p                     *Plugin
}

func (f *fsCaller) executeFunc(path string) {
	stats, err := f.fsFunc(path)

	if err != nil {
		f.errChan <- err

		return
	}

	f.outChanStuckUnchecked <- stats
}

func (f *fsCaller) checkNotStuckAndExecute(path string) {
	if isStuck(path) {
		f.outChanStuckChecked <- fmt.Errorf("mount '%s' is unavailable", path)

		return
	}

	defer func() {
		resetStuck(path)
	}()

	go f.executeFunc(path)

	select {
	case stat := <-f.outChanStuckUnchecked:
		f.outChanStuckChecked <- stat

		return
	case err := <-f.errChan:
		f.outChanStuckChecked <- err

		return
	case <-time.After(timeout * time.Second):
		f.outChanStuckChecked <- fmt.Errorf("operation on mount '%s' timed out", path)
	}

	incStuck(path)

	select {
	case <-f.outChanStuckUnchecked:
		return
	case <-f.errChan:
		return
	case <-time.After(timeout * 12 * 3600 * time.Second):
		return
	}
}

func (f *fsCaller) run(path string) (stat *FsStats, err error) {
	go f.checkNotStuckAndExecute(path)

	v := <-f.outChanStuckChecked

	switch d := v.(type) {
	case *FsStats:
		return d, nil
	case error:
		return nil, d
	default:
		return nil, fmt.Errorf("Unsupported return type %T", d)
	}
}

func isStuck(path string) bool {
	stuckMux.Lock()
	defer stuckMux.Unlock()
	return stuckMounts[path] > 0
}

func resetStuck(path string) {
	stuckMux.Lock()
	defer stuckMux.Unlock()
	stuckMounts[path] = 0
}

func incStuck(path string) {
	stuckMux.Lock()
	defer stuckMux.Unlock()
	stuckMounts[path]++
}

func (p *Plugin) newFSCaller(fsFunc func(path string) (stats *FsStats, err error), fsLen int) *fsCaller {
	fc := fsCaller{}
	fc.fsFunc = fsFunc
	fc.errChan = make(chan error, fsLen)
	fc.outChanStuckUnchecked = make(chan *FsStats, fsLen)
	fc.outChanStuckChecked = make(chan interface{})
	fc.p = p

	return &fc
}

func init() {
	stuckMounts = make(map[string]int)
}
