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

package pdh

import (
	"fmt"
	"strconv"
	"unsafe"

	"zabbix.com/pkg/log"
	"zabbix.com/pkg/win32"

	"golang.org/x/sys/windows"
)

type sysCounter struct {
	name  string
	index string
}

const (
	ObjectSystem = iota
	CounterProcessor
	CounterProcessorInfo
	CounterProcessorTime
	CounterProcessorQueue
	CounterSystemUptime
	ObjectTerminalServices
	CounterTotalSessions
)

var sysCounters []sysCounter = []sysCounter{
	{name: "System"},
	{name: "Processor"},
	{name: "Processor Information"},
	{name: "% Processor time"},
	{name: "Processor Queue Length"},
	{name: "System Up Time"},
	{name: "Terminal Services"},
	{name: "Total Sessions"},
}

const HKEY_PERFORMANCE_TEXT = 0x80000050

func nextField(buf []uint16) (field []uint16, left []uint16) {
	start := -1
	for i, c := range buf {
		if c != 0 {
			start = i
			break
		}
	}
	if start == -1 {
		return []uint16{}, []uint16{}
	}
	for i, c := range buf[start:] {
		if c == 0 {
			return buf[start : start+i], buf[start+i+1:]
		}
	}
	return buf[start:], []uint16{}
}

func locateDefaultCounters() (err error) {
	var size uint32
	counter := windows.StringToUTF16Ptr("Counter")
	err = windows.RegQueryValueEx(HKEY_PERFORMANCE_TEXT, counter, nil, nil, nil, &size)
	if err != nil {
		return
	}
	buf := make([]uint16, size/2)

	err = windows.RegQueryValueEx(HKEY_PERFORMANCE_TEXT, counter, nil, nil, (*byte)(unsafe.Pointer(&buf[0])), &size)
	if err != nil {
		return
	}

	var wcharIndex, wcharName []uint16
	for len(buf) != 0 {
		wcharIndex, buf = nextField(buf)
		if len(wcharIndex) == 0 {
			break
		}
		wcharName, buf = nextField(buf)
		if len(wcharName) == 0 {
			break
		}
		name := windows.UTF16ToString(wcharName)
		for i := range sysCounters {
			if sysCounters[i].name == name {
				sysCounters[i].index = windows.UTF16ToString(wcharIndex)
			}
		}
	}

	return
}

func CounterIndex(id int) (index string) {
	return sysCounters[id].index
}

func CounterPath(object int, counter int) (path string) {
	return fmt.Sprintf(`\%s\%s`, sysCounters[object].index, sysCounters[counter].index)
}

func GetCounterDouble(path string) (value *float64, err error) {
	var query win32.PDH_HQUERY
	if query, err = win32.PdhOpenQuery(nil, 0); err != nil {
		return
	}
	defer func() {
		_ = win32.PdhCloseQuery(query)
	}()

	var counter win32.PDH_HCOUNTER
	if counter, err = win32.PdhAddCounter(query, path, 0); err != nil {
		return
	}
	if err = win32.PdhCollectQueryData(query); err != nil {
		return
	}
	return win32.PdhGetFormattedCounterValueDouble(counter)
}

func GetCounterInt64(path string) (value *int64, err error) {
	var query win32.PDH_HQUERY
	if query, err = win32.PdhOpenQuery(nil, 0); err != nil {
		return
	}
	defer func() {
		_ = win32.PdhCloseQuery(query)
	}()

	var counter win32.PDH_HCOUNTER
	if counter, err = win32.PdhAddCounter(query, path, 0); err != nil {
		return
	}
	if err = win32.PdhCollectQueryData(query); err != nil {
		return
	}
	return win32.PdhGetFormattedCounterValueInt64(counter)
}

func ConvertPath(path string) (outPath string, err error) {
	var elements *win32.PDH_COUNTER_PATH_ELEMENTS
	if elements, err = win32.PdhParseCounterPath(path); err != nil {
		return
	}

	bufObject := (*[1 << 16]uint16)(unsafe.Pointer(elements.ObjectName))[:win32.PDH_MAX_COUNTER_NAME:win32.PDH_MAX_COUNTER_NAME]
	bufCounter := (*[1 << 16]uint16)(unsafe.Pointer(elements.CounterName))[:win32.PDH_MAX_COUNTER_NAME:win32.PDH_MAX_COUNTER_NAME]
	objectName := windows.UTF16ToString(bufObject)
	counterName := windows.UTF16ToString(bufCounter)
	objectIndex, objectErr := strconv.ParseInt(objectName, 10, 32)
	counterIndex, counterErr := strconv.ParseInt(counterName, 10, 32)

	log.Tracef(`parsed performance counter \%s\%s`, objectName, counterName)

	if objectErr != nil && counterErr != nil {
		return path, nil
	}
	if objectErr == nil {
		if objectName, err = win32.PdhLookupPerfNameByIndex(int(objectIndex)); err != nil {
			return
		}
		elements.ObjectName = uintptr(unsafe.Pointer(windows.StringToUTF16Ptr(objectName)))
	}
	if counterErr == nil {
		if counterName, err = win32.PdhLookupPerfNameByIndex(int(counterIndex)); err != nil {
			return
		}
		elements.CounterName = uintptr(unsafe.Pointer(windows.StringToUTF16Ptr(counterName)))
	}

	return win32.PdhMakeCounterPath(elements)
}

func init() {
	if err := locateDefaultCounters(); err != nil {
		panic(err.Error())
	}
}
