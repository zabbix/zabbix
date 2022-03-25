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
	"fmt"
	"encoding/json"
	"errors"
	"regexp"
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

type procStatus struct {
	Pid           uint32   `json:"pid"`
	PPid          uint32   `json:"ppid"`
	Name          string   `json:"name"`
	Cmdline       string   `json:"cmdline"`
	Vmsize        float64  `json:"vmsize"`
	Wkset         float64  `json:"wkset"`
	CpuTimeUser   float64  `json:"cputime_user"`
	CpuTimeSystem float64  `json:"cputime_system"`
	Threads       uint32   `json:"threads"`
	PageFaults    uint32   `json:"page_faults"`
	Handles       int32    `json:"handles"`
	IoReadsB      uint64   `json:"io_read_b"`
	IoWritesB     uint64   `json:"io_write_b"`
	IoOtherB      uint64   `json:"io_other_b"`
	IoReadsOp     uint64   `json:"io_read_op"`
	IoWritesOp    uint64   `json:"io_write_op"`
	IoOtherOp     uint64   `json:"io_other_op"`
	GdiObj	      uint32   `json:"gdiobj"`
	UserObj       uint32   `json:"userobj"`
}

type procSummary struct {
	Name	      string  `json:"name"`
	Processes     int     `json:"processes"`
	Vmsize	      float64 `json:"vmsize"`
	Wkset	      float64 `json:"wkset"`
	CpuTimeUser   float64 `json:"cputime_user"`
	CpuTimeSystem float64 `json:"cputime_system"`
	Threads       uint32  `json:"threads"`
	PageFaults    uint32  `json:"page_faults"`
	IoReadsB      uint64  `json:"io_read_b"`
	IoWritesB     uint64  `json:"io_write_b"`
	IoOtherB      uint64  `json:"io_other_b"`
	IoReadsOp     uint64  `json:"io_read_op"`
	IoWritesOp    uint64  `json:"io_write_op"`
	IoOtherOp     uint64  `json:"io_other_op"`
	GdiObj        uint32  `json:"gdiobj"`
	UserObj       uint32  `json:"userobj"`
}

type thread struct {
	Pid           uint64  `json:"pid"`
	PPid          uint64  `json:"ppid"`
	Name          string  `json:"name"`
	Tid	      uint64  `json:"tid"`
	CpuTimeUser   float64 `json:"cputime_user"`
	CpuTimeSystem float64 `json:"cputime_system"`
	IoOtherB      uint64  `json:"io_other_b"`
	IoReadsOp     uint64  `json:"io_read_op"`
	IoWritesOp    uint64  `json:"io_write_op"`
	IoOtherOp     uint64  `json:"io_other_op"`
	GdiObj        uint32  `json:"gdiobj"`
	UserObj       uint32  `json:"userobj"`
}

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

func (p *Plugin) exportProcGet(params []string) (interface{}, error) {
	var name, userName, cmdline, mode string
	switch len(params) {
	case 4:
		mode = params[3]
		switch mode {
		case "process", "", "thread", "summary":
		default:
			return nil, errors.New("Invalid fourth parameter")
		}
		fallthrough
	case 3:
		cmdline = params[2]
		fallthrough
	case 2:
		userName = params[1]
		fallthrough
	case 1:
		name = params[0]
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	var array []procStatus
	//var threadArray []thread
	var summaryArray []procSummary

	var cmdlinePattern *regexp.Regexp
	var regexpErr error
	if cmdline != "" {
		cmdlinePattern, regexpErr = regexp.Compile(cmdline)
	}

	if mode != "thread" {
		hs, err := syscall.CreateToolhelp32Snapshot(syscall.TH32CS_SNAPPROCESS, 0)
		if err != nil {
			return nil, errors.New("Cannot get process table snapshot")
		}
		defer syscall.CloseHandle(hs)

		var procerr error
		var pe syscall.ProcessEntry32
		pe.Size = uint32(unsafe.Sizeof(pe))

		for procerr = syscall.Process32First(hs, &pe); procerr == nil; procerr = syscall.Process32Next(hs, &pe) {
			procName := windows.UTF16ToString(pe.ExeFile[:])
			if name != "" && procName != name {
				continue
			}

			if mode != "summary" && cmdline != "" && regexpErr == nil &&
				!cmdlinePattern.Match([]byte(procName)) {
					continue
			}

			if userName != "" {
				var uname string
				if uname, err = getProcessUsername(pe.ProcessID); err == nil && uname != userName {
					continue
				}
			}

			proc := procStatus{Pid: pe.ProcessID, PPid: pe.ParentProcessID, Name: procName, Cmdline: procName,
				Threads: pe.Threads,
			}

			// process might not exist anymore already, skipping silently
			h, err := syscall.OpenProcess(windows.PROCESS_QUERY_LIMITED_INFORMATION, false, pe.ProcessID)
			if err != nil {
				continue
			}
			defer syscall.CloseHandle(h)

			if count, err := win32.GetProcessHandleCount(h); err == nil {
				proc.Handles = count
			}

			if m, err := win32.GetProcessMemoryInfo(h); err == nil {
				proc.Vmsize = float64(m.PagefileUsage) / 1024
				proc.Wkset = float64(m.WorkingSetSize) / 1024
				proc.PageFaults = m.PageFaultCount
			}

			if ioc, err := win32.GetProcessIoCounters(h); err == nil {
				proc.IoReadsB = ioc.ReadTransferCount
				proc.IoWritesB = ioc.WriteTransferCount
				proc.IoOtherB = ioc.OtherTransferCount
				proc.IoReadsOp = ioc.ReadOperationCount
				proc.IoWritesOp = ioc.WriteOperationCount
				proc.IoOtherOp = ioc.OtherOperationCount
			}

			var creationTime, exitTime, kernelTime, userTime syscall.Filetime
			if err = syscall.GetProcessTimes(h, &creationTime, &exitTime, &kernelTime, &userTime); err == nil {
				proc.CpuTimeUser = float64((uint64(userTime.HighDateTime)<<32 | uint64(kernelTime.LowDateTime)) / 1e4)
				proc.CpuTimeSystem = float64((uint64(kernelTime.HighDateTime)<<32 | uint64(kernelTime.LowDateTime)) / 1e4)
			}

			proc.GdiObj = win32.GetGuiResources(h, win32.GR_GDIOBJECTS)
			proc.UserObj = win32.GetGuiResources(h, win32.GR_USEROBJECTS)

			array = append(array, proc)
		}
	} else { // mode is "thread"
		hs, err := syscall.CreateToolhelp32Snapshot(syscall.TH32CS_SNAPTHREAD, 0)
		if err != nil {
			return nil, errors.New("Cannot get process table snapshot")
		}
		defer syscall.CloseHandle(hs)
	}
	switch mode {
	case "process", "":
		if jsonArray, err := json.Marshal(array); err == nil {
			return string(jsonArray), nil
		} else {
			return nil, fmt.Errorf("Cannot create JSON array: %s", err)
		}
	case "summary":
		var processed []string
		for i, proc := range array {
			var found bool
			for _, j := range processed {
				if j == proc.Name {
					found = true
					break
				}
			}
			if found == true {
				continue
			}

			procSum := procSummary{proc.Name, 1, proc.Vmsize, proc.Wkset,
				proc.CpuTimeUser, proc.CpuTimeSystem, proc.Threads,
				proc.PageFaults, proc.IoReadsB, proc.IoWritesB, proc.IoOtherB,
				proc.IoReadsOp, proc.IoWritesOp, proc.IoOtherOp,
				proc.GdiObj, proc.UserObj}

			if len(array) > i + 1 {
				for _, procCmp := range array[i + 1:] {
					if procCmp.Name == proc.Name {
						procSum.Processes++
						procSum.Vmsize += procCmp.Vmsize
						procSum.Wkset += procCmp.Wkset
						procSum.CpuTimeUser += procCmp.CpuTimeUser
						procSum.CpuTimeSystem += procCmp.CpuTimeSystem
						procSum.Threads += procCmp.Threads
						procSum.PageFaults += procCmp.PageFaults
						procSum.IoReadsB += procCmp.IoReadsB
						procSum.IoWritesB += procCmp.IoWritesB
						procSum.IoReadsOp += procCmp.IoReadsOp
						procSum.IoWritesOp += procCmp.IoWritesOp
						procSum.IoOtherB += procCmp.IoOtherB
						procSum.IoOtherOp += procCmp.IoOtherOp
					}
				}
			}
			processed = append(processed, proc.Name)
			summaryArray = append(summaryArray, procSum)
		}
		if jsonArray, err := json.Marshal(summaryArray); err == nil {
			return string(jsonArray), nil
		} else {
			return nil, fmt.Errorf("Cannot create JSON array: %s", err)
		}
	}
	return nil, nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "proc.num":
		return p.exportProcNum(params)
	case "proc_info":
		return p.exportProcInfo(params)
	case "proc.get":
		return p.exportProcGet(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func init() {
	plugin.RegisterMetrics(&impl, "Proc",
		"proc.num", "The number of processes.",
		"proc_info", "Various information about specific process(es).",
		"proc.get", "List of OS processes with statistics.",
	)
}
