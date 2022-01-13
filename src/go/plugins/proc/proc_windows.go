/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

package proc

import (
	"errors"
	"strings"
	"syscall"
	"unsafe"

	"golang.org/x/sys/windows"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/win32"
)

const maxName = 256

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

func getProcessUsername(pid uint32) (result string, err error) {
	h, err := syscall.OpenProcess(windows.PROCESS_QUERY_LIMITED_INFORMATION, false, pid)
	if err != nil {
		return
	}
	defer syscall.CloseHandle(h)

	var tok syscall.Token
	if err = syscall.OpenProcessToken(h, windows.TOKEN_QUERY, &tok); err != nil {
		return
	}
	defer syscall.Close(syscall.Handle(tok))

	var size uint32
	err = syscall.GetTokenInformation(tok, syscall.TokenUser, nil, 0, &size)
	if err != nil && err.(syscall.Errno) != syscall.ERROR_INSUFFICIENT_BUFFER {
		return
	}
	b := make([]byte, size)
	err = syscall.GetTokenInformation(tok, syscall.TokenUser, &b[0], size, &size)
	if err != nil {
		return
	}
	sid := (*syscall.Tokenuser)(unsafe.Pointer(&b[0])).User.Sid

	nameLen := uint32(maxName)
	name := make([]uint16, nameLen)
	domainLen := uint32(maxName)
	domain := make([]uint16, domainLen)
	var use uint32
	if err = syscall.LookupAccountSid(nil, sid, &name[0], &nameLen, &domain[0], &domainLen, &use); err != nil {
		return
	}
	return windows.UTF16ToString(name), nil
}

type processEnumerator interface {
	inspect(p *syscall.ProcessEntry32)
}

func enumerateProcesses(name string, pv processEnumerator) (err error) {
	hs, err := syscall.CreateToolhelp32Snapshot(syscall.TH32CS_SNAPPROCESS, 0)
	if err != nil {
		return
	}
	defer syscall.CloseHandle(hs)

	var procerr error
	var pe syscall.ProcessEntry32
	pe.Size = uint32(unsafe.Sizeof(pe))
	name = strings.ToUpper(name)

	for procerr = syscall.Process32First(hs, &pe); procerr == nil; procerr = syscall.Process32Next(hs, &pe) {
		if name == "" || name == strings.ToUpper(windows.UTF16ToString(pe.ExeFile[:])) {
			pv.inspect(&pe)
		}
	}
	if procerr.(syscall.Errno) != syscall.ERROR_NO_MORE_FILES {
		return procerr
	}

	return nil
}

type numEnumerator struct {
	user string
	num  int
}

func (e *numEnumerator) inspect(p *syscall.ProcessEntry32) {
	if e.user != "" {
		if procUser, err := getProcessUsername(p.ProcessID); err != nil || e.user != strings.ToUpper(procUser) {
			return
		}
	}
	e.num++
}

func (p *Plugin) exportProcNum(params []string) (result interface{}, err error) {
	if len(params) > 2 {
		return nil, errors.New("Too many parameters.")
	}
	var name string
	if len(params) > 0 {
		name = params[0]
	}
	var e numEnumerator
	if len(params) > 1 {
		e.user = strings.ToUpper(params[1])
	}

	if err = enumerateProcesses(name, &e); err != nil {
		return
	}
	return e.num, nil
}

type infoAttr int

const (
	attrVmsize infoAttr = iota
	attrWkset
	attrPf
	attrKtime
	attrUtime
	attrGdiobj
	attrUserobj
	attrIoReadB
	attrIoReadOp
	attrIoWriteB
	attrIoWriteOp
	attrIoOtherB
	attrIoOtherOp
)

var attrMap map[string]infoAttr = map[string]infoAttr{
	"":            attrVmsize,
	"vmsize":      attrVmsize,
	"wkset":       attrWkset,
	"pf":          attrPf,
	"ktime":       attrKtime,
	"utime":       attrUtime,
	"gdiobj":      attrGdiobj,
	"userobj":     attrUserobj,
	"io_read_b":   attrIoReadB,
	"io_read_op":  attrIoReadOp,
	"io_write_b":  attrIoWriteB,
	"io_write_op": attrIoWriteOp,
	"io_other_b":  attrIoOtherB,
	"io_other_op": attrIoOtherOp,
}

type infoStat int

const (
	statAvg infoStat = iota
	statMin
	statMax
	statSum
)

var statMap map[string]infoStat = map[string]infoStat{
	"":    statAvg,
	"avg": statAvg,
	"min": statMin,
	"max": statMax,
	"sum": statSum,
}

type infoEnumerator struct {
	attr  infoAttr
	stat  infoStat
	value float64
	num   int
}

func (e *infoEnumerator) inspect(p *syscall.ProcessEntry32) {
	h, err := syscall.OpenProcess(windows.PROCESS_QUERY_LIMITED_INFORMATION, false, p.ProcessID)
	if err != nil {
		return
	}
	defer syscall.CloseHandle(h)

	var value float64
	switch e.attr {
	case attrVmsize:
		m, err := win32.GetProcessMemoryInfo(h)
		if err != nil {
			return
		}
		// convert to kilobytes
		value = float64(m.PagefileUsage) / 1024
	case attrWkset:
		m, err := win32.GetProcessMemoryInfo(h)
		if err != nil {
			return
		}
		// convert to kilobytes
		value = float64(m.WorkingSetSize) / 1024
	case attrPf:
		m, err := win32.GetProcessMemoryInfo(h)
		if err != nil {
			return
		}
		value = float64(m.PageFaultCount)
	case attrKtime:
		var creationTime, exitTime, kernelTime, userTime syscall.Filetime
		if err = syscall.GetProcessTimes(h, &creationTime, &exitTime, &kernelTime, &userTime); err != nil {
			return
		}
		value = float64((uint64(kernelTime.HighDateTime)<<32 | uint64(kernelTime.LowDateTime)) / 1e4)
	case attrUtime:
		var creationTime, exitTime, kernelTime, userTime syscall.Filetime
		if err = syscall.GetProcessTimes(h, &creationTime, &exitTime, &kernelTime, &userTime); err != nil {
			return
		}
		value = float64((uint64(userTime.HighDateTime)<<32 | uint64(userTime.LowDateTime)) / 1e4)
	case attrGdiobj:
		value = float64(win32.GetGuiResources(h, win32.GR_GDIOBJECTS))
	case attrUserobj:
		value = float64(win32.GetGuiResources(h, win32.GR_USEROBJECTS))
	case attrIoReadB:
		if ioc, err := win32.GetProcessIoCounters(h); err != nil {
			return
		} else {
			value = float64(ioc.ReadTransferCount)
		}
	case attrIoReadOp:
		if ioc, err := win32.GetProcessIoCounters(h); err != nil {
			return
		} else {
			value = float64(ioc.ReadOperationCount)
		}
	case attrIoWriteB:
		if ioc, err := win32.GetProcessIoCounters(h); err != nil {
			return
		} else {
			value = float64(ioc.WriteTransferCount)
		}
	case attrIoWriteOp:
		if ioc, err := win32.GetProcessIoCounters(h); err != nil {
			return
		} else {
			value = float64(ioc.WriteOperationCount)
		}
	case attrIoOtherB:
		if ioc, err := win32.GetProcessIoCounters(h); err != nil {
			return
		} else {
			value = float64(ioc.OtherTransferCount)
		}
	case attrIoOtherOp:
		if ioc, err := win32.GetProcessIoCounters(h); err != nil {
			return
		} else {
			value = float64(ioc.OtherOperationCount)
		}
	}

	switch e.stat {
	case statAvg, statSum:
		e.value += value
	case statMin:
		if e.num == 0 || value < e.value {
			e.value = value
		}
	case statMax:
		if e.num == 0 || value > e.value {
			e.value = value
		}
	}
	e.num++
}

func (p *Plugin) exportProcInfo(params []string) (result interface{}, err error) {
	if len(params) > 3 {
		return nil, errors.New("Too many parameters.")
	}

	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}
	name := params[0]

	var e infoEnumerator
	if len(params) > 1 {
		var ok bool
		if e.attr, ok = attrMap[params[1]]; !ok {
			return nil, errors.New("Invalid second parameter.")
		}
	}
	if len(params) > 2 {
		var ok bool
		if e.stat, ok = statMap[params[2]]; !ok {
			return nil, errors.New("Invalid third parameter.")
		}
	}

	if err = enumerateProcesses(name, &e); err != nil {
		return
	}
	if e.stat == statAvg {
		e.value = e.value / float64(e.num)
	}
	return e.value, nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "proc.num":
		return p.exportProcNum(params)
	case "proc_info":
		return p.exportProcInfo(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func init() {
	plugin.RegisterMetrics(&impl, "Proc",
		"proc.num", "The number of processes.",
		"proc_info", "Various information about specific process(es).",
	)
}
