//go:build windows
// +build windows

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

package win32

import (
	"errors"
	"fmt"
	"syscall"
	"time"
	"unsafe"

	"golang.org/x/sys/windows"
	"golang.zabbix.com/sdk/log"
)

const (
	PDH_CSTATUS_VALID_DATA   = 0x00000000
	PDH_CSTATUS_NEW_DATA     = 0x00000001
	PDH_CSTATUS_INVALID_DATA = 0xC0000BBA

	PDH_MORE_DATA                 = 0x800007D2
	PDH_NO_DATA                   = 0x800007D5
	PDH_INVALID_DATA              = 0xc0000bc6
	PDH_CALC_NEGATIVE_DENOMINATOR = 0x800007D6

	PDH_FMT_DOUBLE   = 0x00000200
	PDH_FMT_LARGE    = 0x00000400
	PDH_FMT_NOCAP100 = 0x00008000

	PDH_MAX_COUNTER_NAME = 1024

	PERF_DETAIL_WIZARD = 400
)

var (
	NegDenomErr = newPdhError(PDH_CALC_NEGATIVE_DENOMINATOR)

	hPdh                        = mustLoadLibrary("pdh.dll")
	pdhOpenQuery                = hPdh.mustGetProcAddress("PdhOpenQuery")
	pdhCloseQuery               = hPdh.mustGetProcAddress("PdhCloseQuery")
	pdhAddCounter               = hPdh.mustGetProcAddress("PdhAddCounterW")
	pdhAddEnglishCounter        = hPdh.mustGetProcAddress("PdhAddEnglishCounterW")
	pdhCollectQueryData         = hPdh.mustGetProcAddress("PdhCollectQueryData")
	pdhGetFormattedCounterValue = hPdh.mustGetProcAddress("PdhGetFormattedCounterValue")
	pdhParseCounterPath         = hPdh.mustGetProcAddress("PdhParseCounterPathW")
	pdhMakeCounterPath          = hPdh.mustGetProcAddress("PdhMakeCounterPathW")
	pdhLookupPerfNameByIndex    = hPdh.mustGetProcAddress("PdhLookupPerfNameByIndexW")
	pdhLookupPerfIndexByName    = hPdh.mustGetProcAddress("PdhLookupPerfIndexByNameW")
	pdhRemoveCounter            = hPdh.mustGetProcAddress("PdhRemoveCounter")
	pdhEnumObjectItems          = hPdh.mustGetProcAddress("PdhEnumObjectItemsW")
	pdhEnumObjects              = hPdh.mustGetProcAddress("PdhEnumObjectsW")
)

type Instance struct {
	Name string `json:"{#INSTANCE}"`
}

func PdhOpenQuery(dataSource *string, userData uintptr) (query PDH_HQUERY, err error) {
	var source uintptr
	if dataSource != nil {
		wcharSource := windows.StringToUTF16(*dataSource)
		source = uintptr(unsafe.Pointer(&wcharSource[0]))
	}
	ret, _, _ := syscall.Syscall(pdhOpenQuery, 3, source, userData, uintptr(unsafe.Pointer(&query)))

	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return 0, newPdhError(ret)
	}
	return
}

func PdhCloseQuery(query PDH_HQUERY) (err error) {
	ret, _, _ := syscall.Syscall(pdhCloseQuery, 1, uintptr(query), 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return newPdhError(ret)
	}
	return nil
}

func PdhAddCounter(query PDH_HQUERY, path string, userData uintptr) (counter PDH_HCOUNTER, err error) {
	wcharPath, _ := syscall.UTF16PtrFromString(path)
	ret, _, _ := syscall.Syscall6(pdhAddCounter, 4, uintptr(query), uintptr(unsafe.Pointer(wcharPath)), userData,
		uintptr(unsafe.Pointer(&counter)), 0, 0)

	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return 0, newPdhError(ret)
	}
	return
}

func PdhAddEnglishCounter(query PDH_HQUERY, path string, userData uintptr) (counter PDH_HCOUNTER, err error) {
	wcharPath, _ := syscall.UTF16PtrFromString(path)
	ret, _, _ := syscall.Syscall6(pdhAddEnglishCounter, 4, uintptr(query), uintptr(unsafe.Pointer(wcharPath)), userData,
		uintptr(unsafe.Pointer(&counter)), 0, 0)

	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return 0, newPdhError(ret)
	}
	return
}

func PdhCollectQueryData(query PDH_HQUERY) (err error) {
	ret, _, _ := syscall.Syscall(pdhCollectQueryData, 1, uintptr(query), 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return newPdhError(ret)
	}
	return nil
}

func PdhGetFormattedCounterValueDouble(counter PDH_HCOUNTER, tryCount int) (*float64, error) {
	tryCount--

	value, err := getCounterValueDouble(counter)
	if err != nil {
		if !errors.Is(err, NegDenomErr) || tryCount <= 0 {
			return nil, err
		}

		log.Debugf(
			"Detected performance counter with negative denominator, retrying in 1 second",
		)

		time.Sleep(time.Second)

		return PdhGetFormattedCounterValueDouble(counter, tryCount)
	}

	return value, nil
}

func PdhGetFormattedCounterValueInt64(counter PDH_HCOUNTER) (value *int64, err error) {
	return PdhGetFormattedCounterValueInt64Helper(counter, true)
}

func PdhGetFormattedCounterValueInt64Helper(counter PDH_HCOUNTER, retry bool) (value *int64, err error) {
	var pdhValue PDH_FMT_COUNTERVALUE_LARGE
	ret, _, _ := syscall.Syscall6(pdhGetFormattedCounterValue, 4, uintptr(counter), uintptr(PDH_FMT_LARGE), 0,
		uintptr(unsafe.Pointer(&pdhValue)), 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		if ret == PDH_CALC_NEGATIVE_DENOMINATOR {
			if retry {
				log.Debugf("Detected performance counter with negative denominator, retrying in 1 second")
				time.Sleep(time.Second)
				return PdhGetFormattedCounterValueInt64Helper(counter, false)
			}
			log.Warningf("Detected performance counter with negative denominator the second time after retry, giving up...")
		} else if ret == PDH_INVALID_DATA || ret == PDH_CSTATUS_INVALID_DATA {
			return nil, nil
		}
		return nil, newPdhError(ret)
	}
	return &pdhValue.Value, nil
}

func PdhRemoveCounter(counter PDH_HCOUNTER) (err error) {
	ret, _, _ := syscall.Syscall(pdhRemoveCounter, 1, uintptr(counter), 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return newPdhError(ret)
	}
	return nil
}

func PdhParseCounterPath(path string) (elements *PDH_COUNTER_PATH_ELEMENTS, err error) {
	wPath := windows.StringToUTF16(path)
	ptrPath := uintptr(unsafe.Pointer(&wPath[0]))
	var size uint32
	ret, _, _ := syscall.Syscall6(pdhParseCounterPath, 4, ptrPath, 0, uintptr(unsafe.Pointer(&size)), 0, 0, 0)
	if ret != PDH_MORE_DATA && syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return nil, newPdhError(ret)
	}

	buf := make([]uint16, size/2)
	ret, _, _ = syscall.Syscall6(pdhParseCounterPath, 4, ptrPath, uintptr(unsafe.Pointer(&buf[0])),
		uintptr(unsafe.Pointer(&size)), 0, 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return nil, newPdhError(ret)
	}
	return LP_PDH_COUNTER_PATH_ELEMENTS(unsafe.Pointer(&buf[0])), nil
}

func PdhMakeCounterPath(elements *PDH_COUNTER_PATH_ELEMENTS) (path string, err error) {
	size := uint32(PDH_MAX_COUNTER_NAME)
	buf := make([]uint16, size)
	ret, _, _ := syscall.Syscall6(pdhMakeCounterPath, 4, uintptr(unsafe.Pointer(elements)),
		uintptr(unsafe.Pointer(&buf[0])), uintptr(unsafe.Pointer(&size)), 0, 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return "", newPdhError(ret)
	}
	return windows.UTF16ToString(buf), nil
}

func PdhLookupPerfNameByIndex(index int) (path string, err error) {
	size := uint32(PDH_MAX_COUNTER_NAME)
	buf := make([]uint16, size)
	ret, _, _ := syscall.Syscall6(pdhLookupPerfNameByIndex, 4, 0, uintptr(index), uintptr(unsafe.Pointer(&buf[0])),
		uintptr(unsafe.Pointer(&size)), 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return "", newPdhError(ret)
	}
	return windows.UTF16ToString(buf), nil
}

func PdhLookupPerfIndexByName(name string) (idx int, err error) {
	nameUTF16, err := syscall.UTF16PtrFromString(name)
	if err != nil {
		return 0, err
	}

	ret, _, _ := syscall.Syscall(pdhLookupPerfIndexByName, 3, 0, uintptr(unsafe.Pointer(nameUTF16)), uintptr(unsafe.Pointer(&idx)))
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return 0, newPdhError(ret)
	}

	return idx, nil
}

func PdhEnumObjectItems(objectName string) (instances []Instance, err error) {
	var counterListSize, instanceListSize uint32
	nameUTF16, err := syscall.UTF16FromString(objectName)
	if err != nil {
		return nil, err
	}
	ptrNameUTF16 := uintptr(unsafe.Pointer(&nameUTF16[0]))

	ret, _, _ := syscall.Syscall9(pdhEnumObjectItems, 9, 0, 0,
		ptrNameUTF16, 0, uintptr(unsafe.Pointer(&counterListSize)), 0,
		uintptr(unsafe.Pointer(&instanceListSize)), uintptr(PERF_DETAIL_WIZARD), 0)
	if ret != PDH_MORE_DATA {
		if syscall.Errno(ret) == windows.ERROR_SUCCESS {
			return
		}
		return nil, newPdhError(ret)
	}
	var instptr uintptr
	var instbuf []uint16

	if counterListSize < 1 {
		return nil, fmt.Errorf("No counters found for given object.")
	}

	counterbuf := make([]uint16, counterListSize)

	for {
		if instanceListSize == 0 {
			return nil, fmt.Errorf("Object does not support variable instances.")
		}

		instbuf = make([]uint16, instanceListSize)
		instptr = uintptr(unsafe.Pointer(&instbuf[0]))

		ret, _, _ = syscall.Syscall9(pdhEnumObjectItems, 9, 0, 0, ptrNameUTF16, uintptr(unsafe.Pointer(&counterbuf[0])),
			uintptr(unsafe.Pointer(&counterListSize)), instptr, uintptr(unsafe.Pointer(&instanceListSize)),
			uintptr(PERF_DETAIL_WIZARD), 0)
		if ret == PDH_MORE_DATA {
			continue
		}
		if syscall.Errno(ret) != windows.ERROR_SUCCESS {
			return nil, newPdhError(ret)
		}

		break
	}

	var singleName []uint16
	m := make(map[string]bool)
	for len(instbuf) != 0 {
		singleName, instbuf = NextField(instbuf)
		if len(singleName) == 0 {
			break
		}

		strName := windows.UTF16ToString(singleName)
		if _, ok := m[strName]; !ok {
			m[strName] = true
			instances = append(instances, Instance{strName})
		}
	}

	return instances, nil
}

func PdhEnumObject() (objects []string, err error) {
	var objectListSize uint32
	ret, _, _ := syscall.Syscall6(pdhEnumObjects, 6, 0, 0, 0, uintptr(unsafe.Pointer(&objectListSize)),
		uintptr(PERF_DETAIL_WIZARD), bool2uintptr(true))
	if ret != PDH_MORE_DATA {
		return nil, newPdhError(ret)
	}

	if objectListSize < 1 {
		return nil, fmt.Errorf("No objects found.")
	}

	objectBuf := make([]uint16, objectListSize)
	ret, _, _ = syscall.Syscall6(pdhEnumObjects, 6, 0, 0, uintptr(unsafe.Pointer(&objectBuf[0])),
		uintptr(unsafe.Pointer(&objectListSize)), uintptr(PERF_DETAIL_WIZARD),
		bool2uintptr(false))
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return nil, newPdhError(ret)
	}

	var singleName []uint16

	for len(objectBuf) != 0 {
		singleName, objectBuf = NextField(objectBuf)
		if len(singleName) == 0 {
			break
		}
		objects = append(objects, windows.UTF16ToString(singleName))
	}

	return objects, nil
}

func getCounterValueDouble(counter PDH_HCOUNTER) (*float64, error) {
	var pdhValue PDH_FMT_COUNTERVALUE_DOUBLE

	ret, _, _ := syscall.SyscallN(
		pdhGetFormattedCounterValue,
		uintptr(counter),
		uintptr(PDH_FMT_DOUBLE|PDH_FMT_NOCAP100),
		0,
		uintptr(unsafe.Pointer(&pdhValue)),
	)

	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		if ret == PDH_INVALID_DATA || ret == PDH_CSTATUS_INVALID_DATA {
			return nil, nil
		}

		return nil, newPdhError(ret)
	}

	return &pdhValue.Value, nil
}

func newPdhError(ret uintptr) (err error) {
	flags := uint32(windows.FORMAT_MESSAGE_FROM_HMODULE | windows.FORMAT_MESSAGE_IGNORE_INSERTS)
	var len uint32
	buf := make([]uint16, 1024)

	len, err = windows.FormatMessage(flags, uintptr(hPdh), uint32(ret), 0, buf, nil)
	if len == 0 {
		return
	}

	return errors.New(windows.UTF16ToString(buf))
}
