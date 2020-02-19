// +build windows

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

package win32

import (
	"errors"
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
)

var (
	hPdh Hlib

	pdhOpenQuery                uintptr
	pdhCloseQuery               uintptr
	pdhAddCounter               uintptr
	pdhAddEnglishCounter        uintptr
	pdhCollectQueryData         uintptr
	pdhGetFormattedCounterValue uintptr
	pdhParseCounterPath         uintptr
	pdhMakeCounterPath          uintptr
	pdhLookupPerfNameByIndex    uintptr
	pdhRemoveCounter            uintptr
)

const (
	PDH_CSTATUS_VALID_DATA   = 0x00000000
	PDH_CSTATUS_NEW_DATA     = 0x00000001
	PDH_CSTATUS_INVALID_DATA = 0xC0000BBA

	PDH_MORE_DATA    = 0x800007D2
	PDH_NO_DATA      = 0x800007D5
	PDH_INVALID_DATA = 0xc0000bc6

	PDH_FMT_DOUBLE   = 0x00000200
	PDH_FMT_LARGE    = 0x00000400
	PDH_FMT_NOCAP100 = 0x00008000

	PDH_MAX_COUNTER_NAME = 1024
)

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
	ret, _, _ := syscall.Syscall6(pdhAddCounter, 1, uintptr(query), uintptr(unsafe.Pointer(wcharPath)), userData,
		uintptr(unsafe.Pointer(&counter)), 0, 0)

	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		return 0, newPdhError(ret)
	}
	return
}

func PdhAddEnglishCounter(query PDH_HQUERY, path string, userData uintptr) (counter PDH_HCOUNTER, err error) {
	wcharPath, _ := syscall.UTF16PtrFromString(path)
	ret, _, _ := syscall.Syscall6(pdhAddEnglishCounter, 1, uintptr(query), uintptr(unsafe.Pointer(wcharPath)), userData,
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

func PdhGetFormattedCounterValueDouble(counter PDH_HCOUNTER) (value *float64, err error) {
	var pdhValue PDH_FMT_COUNTERVALUE_DOUBLE
	ret, _, _ := syscall.Syscall6(pdhGetFormattedCounterValue, 6, uintptr(counter),
		uintptr(PDH_FMT_DOUBLE|PDH_FMT_NOCAP100), 0, uintptr(unsafe.Pointer(&pdhValue)), 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		if ret == PDH_INVALID_DATA || ret == PDH_CSTATUS_INVALID_DATA {
			return nil, nil
		}
		return nil, newPdhError(ret)
	}
	return &pdhValue.Value, nil
}

func PdhGetFormattedCounterValueInt64(counter PDH_HCOUNTER) (value *int64, err error) {
	var pdhValue PDH_FMT_COUNTERVALUE_LARGE
	ret, _, _ := syscall.Syscall6(pdhGetFormattedCounterValue, 6, uintptr(counter), uintptr(PDH_FMT_LARGE), 0,
		uintptr(unsafe.Pointer(&pdhValue)), 0, 0)
	if syscall.Errno(ret) != windows.ERROR_SUCCESS {
		if ret == PDH_INVALID_DATA || ret == PDH_CSTATUS_INVALID_DATA {
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

func init() {
	hPdh = mustLoadLibrary("pdh.dll")

	pdhOpenQuery = hPdh.mustGetProcAddress("PdhOpenQuery")
	pdhCloseQuery = hPdh.mustGetProcAddress("PdhCloseQuery")
	pdhAddCounter = hPdh.mustGetProcAddress("PdhAddCounterW")
	pdhAddEnglishCounter = hPdh.mustGetProcAddress("PdhAddEnglishCounterW")
	pdhCollectQueryData = hPdh.mustGetProcAddress("PdhCollectQueryData")
	pdhGetFormattedCounterValue = hPdh.mustGetProcAddress("PdhGetFormattedCounterValue")
	pdhParseCounterPath = hPdh.mustGetProcAddress("PdhParseCounterPathW")
	pdhMakeCounterPath = hPdh.mustGetProcAddress("PdhMakeCounterPathW")
	pdhLookupPerfNameByIndex = hPdh.mustGetProcAddress("PdhLookupPerfNameByIndexW")
	pdhRemoveCounter = hPdh.mustGetProcAddress("PdhRemoveCounter")
}
