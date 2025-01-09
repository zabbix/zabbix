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

package pdh

import (
	"fmt"
	"strconv"
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/log"
)

var ObjectsNames map[string]string

type sysCounter struct {
	name  string
	index string
}

const (
	ObjectSystem = iota
	ObjectProcessor
	ObjectProcessorInfo
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
const HKEY_PERFORMANCE_NLSTEXT = 0x80000060

type CounterPathElements struct {
	MachineName    string
	ObjectName     string
	InstanceName   string
	ParentInstance string
	InstanceIndex  int
	CounterName    string
}

func LocateObjectsAndDefaultCounters(resetDefCounters bool) (err error) {
	ObjectsNames = make(map[string]string)

	locNames, err := win32.PdhEnumObject()
	if err != nil {
		return err
	}

	englishCounters := make(map[string]int)

	engBuf, err := getRegQueryCounters(HKEY_PERFORMANCE_NLSTEXT)
	if err == nil {
		var wcharEngIndex, wcharEngName []uint16

		for len(engBuf) != 0 {
			wcharEngIndex, engBuf = win32.NextField(engBuf)
			if len(wcharEngIndex) == 0 {
				break
			}
			wcharEngName, engBuf = win32.NextField(engBuf)
			if len(wcharEngName) == 0 {
				break
			}

			idx, err := strconv.Atoi(windows.UTF16ToString(wcharEngIndex))
			if err != nil {
				return err
			}
			englishCounters[windows.UTF16ToString(wcharEngName)] = idx
		}
	} else {
		log.Warningf("cannot read localized object names: %s", err.Error())
	}

	objectsLocal := make(map[int]string)
	for _, name := range locNames {
		if idx, ok := englishCounters[name]; ok {
			objectsLocal[idx] = name
			continue
		}

		ObjectsNames[name] = name // use local object name as english name if there is no idx that can be used for translation
	}

	buf, err := getRegQueryCounters(HKEY_PERFORMANCE_TEXT)
	if err != nil {
		return
	}

	var wcharIndex, wcharName []uint16
	for len(buf) != 0 {
		wcharIndex, buf = win32.NextField(buf)
		if len(wcharIndex) == 0 {
			break
		}
		wcharName, buf = win32.NextField(buf)
		if len(wcharName) == 0 {
			break
		}

		name := windows.UTF16ToString(wcharName)
		idx := windows.UTF16ToString(wcharIndex)
		if resetDefCounters {
			for i := range sysCounters {
				if sysCounters[i].name == name {
					sysCounters[i].index = idx
				}
			}
		}

		i, err := strconv.Atoi(idx)
		if err != nil {
			return err
		}

		if objectLocal, ok := objectsLocal[i]; ok {
			ObjectsNames[name] = objectLocal
		}
	}

	return
}

func getRegQueryCounters(key windows.Handle) (buf []uint16, err error) {
	var size uint32
	counter := windows.StringToUTF16Ptr("Counter")

	err = windows.RegQueryValueEx(key, counter, nil, nil, nil, &size)
	if err != nil {
		return nil, err
	}

	buf = make([]uint16, size/2)
	err = windows.RegQueryValueEx(key, counter, nil, nil, (*byte)(unsafe.Pointer(&buf[0])), &size)

	return
}

func CounterIndex(id int) (index string) {
	return sysCounters[id].index
}

func CounterName(id int) (name string) {
	return sysCounters[id].name
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
	return win32.PdhGetFormattedCounterValueDouble(counter, 2)
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

	bufObject := (*win32.RGWSTR)(unsafe.Pointer(elements.ObjectName))[:win32.PDH_MAX_COUNTER_NAME:win32.PDH_MAX_COUNTER_NAME]
	bufCounter := (*win32.RGWSTR)(unsafe.Pointer(elements.CounterName))[:win32.PDH_MAX_COUNTER_NAME:win32.PDH_MAX_COUNTER_NAME]
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

func uft16PtrFromStringZ(s string) (ptr uintptr, err error) {
	if s == "" {
		return 0, nil
	}
	p, err := syscall.UTF16PtrFromString(s)
	return uintptr(unsafe.Pointer(p)), err
}

func MakePath(elements *CounterPathElements) (path string, err error) {
	var cpe win32.PDH_COUNTER_PATH_ELEMENTS
	if cpe.MachineName, err = uft16PtrFromStringZ(elements.MachineName); err != nil {
		return
	}
	if cpe.ObjectName, err = uft16PtrFromStringZ(elements.ObjectName); err != nil {
		return
	}
	if cpe.InstanceName, err = uft16PtrFromStringZ(elements.InstanceName); err != nil {
		return
	}
	if cpe.ParentInstance, err = uft16PtrFromStringZ(elements.ParentInstance); err != nil {
		return
	}
	if cpe.CounterName, err = uft16PtrFromStringZ(elements.CounterName); err != nil {
		return
	}
	cpe.InstanceIndex = uint32(elements.InstanceIndex)
	return win32.PdhMakeCounterPath(&cpe)
}
