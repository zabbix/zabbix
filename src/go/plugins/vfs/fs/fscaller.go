/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

package vfsfs

import (
	"reflect"
	"fmt"
	"sync"
	"time"
	"math/rand"
	"git.zabbix.com/ap/plugin-support/log"
)

const timeout = 1

var stuckMounts map[string]int
var stuckMux sync.Mutex

type fsCaller struct {
	fsFunc  func(path string) (stats *FsStats, err error)
	paths   []string
	errChan chan error
	outChan chan *FsStats
	p       *Plugin
}



func (f *fsCaller) execute(path string, x int) {

//		log.Infof("\t\tR0: %d", x)
	rand.Seed(time.Now().UnixNano())
	min := 1
	max := 20000
	r := rand.Intn(max - min + 1) + min
	log.Infof("S: %d", r)
time.Sleep(time.Duration(r)*time.Microsecond)

//	startTime := time.Now()

	stats, err := f.fsFunc(path)

	//	diff := time.Since(startTime)

//	log.Infof("\t\tREAL TIME DIFF: %d", diff)
//	log.Infof("\t\tR1: %d", x)

	defer func() {
		stuckMux.Lock()
		stuckMounts[path] = 0

		//		log.Infof("\t\tR2: %d, and stuckMounts: %d", x, stuckMounts[path])
		stuckMux.Unlock()
	}()

	if err != nil {
		f.errChan <- err
		return
	}

	f.outChan <- stats

//	log.Infof("\t\tR14: %d", x)
}

func (f *fsCaller) invoke(path string, cc chan interface{}, r int) (stat *FsStats, err error) {

	//c2 := make(chan interface{})

	if isStuck(path) {
		return nil, fmt.Errorf("mount '%s' is unavailable", path)
	}

	go f.execute(path, r)

	for {

//		log.Infof("BAGDER invoke for, %d", r)

		select {
		case stat := <-f.outChan:

	//stuckMux.Lock()
	//		log.Infof("BAGDER invokeX1: %d, stuckMounts: %d, chan len: %d", r, stuckMounts[path], len(cc))
	//stuckMux.Unlock()

		defer func() {
				stuckMux.Lock()
				stuckMounts[path] = 0

//				log.Infof("BAGDER invokeX12: %d, stuckMounts: %d, chan len: %d", r, stuckMounts[path], len(cc))
				stuckMux.Unlock()

			}()
			if (len(cc) == 2) {
				log.Infof("AGS")
				for l := 1; l <=2; l++ {
					v := <- cc
					log.Infof("AGS: %d, and type: %T ", r, reflect.TypeOf(v))

					switch d := v.(type) {
					case *FsStats:
						log.Infof("AGS: %d", d)
						return d, nil
					case error:
						log.Infof("AGS: %s", d)
						return nil, d
					default:
						log.Infof("\t\t\t\tAGS: %d", r)
					return nil, fmt.Errorf("AGS Unsupported return typeL %T", d)
							}
						}
				}

			cc <- stat


			return stat, nil
		case err := <-f.errChan:

//	stuckMux.Lock()
//			log.Infof("BAGDER invokeX3: %d, stuckMounts: %d", r, stuckMounts[path])
//stuckMux.Unlock()

			defer func() {
				stuckMux.Lock()

		//		log.Infof("BAGDER invokeX32 for 2: %d, and stuckMounts: %d", r, stuckMounts[path])
				stuckMounts[path] = 0
				stuckMux.Unlock()
			}()

			cc <- err
			return nil, err
		case <-time.After(timeout * time.Second):
		//case <-time.After(5000 * time.Microsecond):
		//case <-time.After(time.Duration(r)*time.Microsecond):
		//	log.Infof("BADGER TIMEOUT CHANNEL LEN: %d", len(f.outChan))
			stuckMux.Lock()
		//	log.Infof("BAGDER TIMEOUT CHANNEL 1: %d, and stuckMounts: %d", r, stuckMounts[path])
			stuckMounts[path]++
			stuckMux.Unlock()
	//		return nil, fmt.Errorf("operation on mount '%s' timed out", path)

		//	log.Infof("BAGDER TIMEOUT CHANNEL 2: %d", r)

		//	log.Infof("BADGER TIMEOUT END out 1 CHANNEL LEN: %d", len(f.outChan))
		//	log.Infof("BADGER TIMEOUT END cc 1 CHANNEL LEN: %d", len(cc))
			cc <- fmt.Errorf("operation on mount '%s' timed out", path)

		//	log.Infof("BADGER TIMEOUT END out 2 CHANNEL LEN: %d", len(f.outChan))
		//	log.Infof("BADGER TIMEOUT END cc 2 CHANNEL LEN: %d", len(cc))
		}
	}
}

func (f *fsCaller) run(path string) (stat *FsStats, err error) {
	cc := make(chan interface{}, 2)

	rand.Seed(time.Now().UnixNano())
	min := 1
	max := 10000
	r := rand.Intn(max - min + 1) + min
	//log.Infof("S: %d", r)

	go f.invoke(path, cc, r)

	//log.Infof("S2: %d, cc(len): %d", r, len(cc))
	v := <- cc
	//log.Infof("S3: %d, and type: %T ", r, reflect.TypeOf(v))

	switch d := v.(type) {
		case *FsStats:
			//log.Infof("\t\t\t\tE0: %d", r)
			return d, nil
		case error:
			//log.Infof("\t\t\t\tE2: %d and error: %+v", r, d)
			return nil, d
		default:
			//log.Infof("\t\t\t\tE3: %d", r)
			return nil, fmt.Errorf("Unsupported return typeL %T", d)
	}
}

func isStuck(path string) bool {
	stuckMux.Lock()
	defer stuckMux.Unlock()
	//log.Infof("SSS ISSTUCK %d", stuckMounts[path])
	return stuckMounts[path] > 0
}

func (p *Plugin) newFSCaller(fsFunc func(path string) (stats *FsStats, err error), fsLen int) *fsCaller {
	fc := fsCaller{}
	fc.fsFunc = fsFunc
	fc.errChan = make(chan error, fsLen)
	fc.outChan = make(chan *FsStats, fsLen)
	fc.p = p

	return &fc
}

func init() {
	stuckMounts = make(map[string]int)
}
