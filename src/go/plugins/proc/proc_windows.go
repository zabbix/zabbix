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
	"strings"
	"syscall"
	"unsafe"

	"git.zabbix.com/ap/plugin-support/plugin"
	"golang.org/x/sys/windows"
	"zabbix.com/pkg/win32"
)

const maxName = 256

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

func getProcessUsername(pid uint32, wsid bool) (result string, sidStr string, err error) {
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

	result = windows.UTF16ToString(name)

	if wsid == true {
		sidStr, err = sid.String()
	}

	return
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
		if procUser, _, err := getProcessUsername(p.ProcessID, false); err != nil || e.user != strings.ToUpper(procUser) {
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
	User          string   `json:"user"`
	Sid           string   `json:"sid"`
	Vmsize        float64  `json:"vmsize"`
	Wkset         float64  `json:"wkset"`
	CpuTimeUser   float64  `json:"cputime_user"`
	CpuTimeSystem float64  `json:"cputime_system"`
	Threads       uint32   `json:"threads"`
	PageFaults    int64    `json:"page_faults"`
	Handles       int64    `json:"handles"`
	IoReadsB      int64    `json:"io_read_b"`
	IoWritesB     int64    `json:"io_write_b"`
	IoReadsOp     int64    `json:"io_read_op"`
	IoWritesOp    int64    `json:"io_write_op"`
	IoOtherB      int64    `json:"io_other_b"`
	IoOtherOp     int64    `json:"io_other_op"`
}

type procSummary struct {
	Name	      string  `json:"name"`
	Processes     int     `json:"processes"`
	Vmsize	      float64 `json:"vmsize"`
	Wkset	      float64 `json:"wkset"`
	CpuTimeUser   float64 `json:"cputime_user"`
	CpuTimeSystem float64 `json:"cputime_system"`
	Threads       uint32  `json:"threads"`
	PageFaults    int64   `json:"page_faults"`
	Handles       int64   `json:"handles"`
	IoReadsB      int64   `json:"io_read_b"`
	IoWritesB     int64   `json:"io_write_b"`
	IoReadsOp     int64   `json:"io_read_op"`
	IoWritesOp    int64   `json:"io_write_op"`
	IoOtherB      int64   `json:"io_other_b"`
	IoOtherOp     int64   `json:"io_other_op"`
}

type thread struct {
	Pid           uint32  `json:"pid"`
	PPid          uint32  `json:"ppid"`
	Name          string  `json:"name"`
	User          string  `json:"user"`
	Sid           string  `json:"sid"`
	Tid           uint32  `json:"tid"`
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
	var name, userName, mode string
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
		if params[2] != "" {
			return nil, errors.New("Invalid third parameter")
		}
		fallthrough
	case 2:
		userName = strings.ToUpper(params[1])
		fallthrough
	case 1:
		name = params[0]
	case 0:
	default:
		return nil, errors.New("Too many parameters.")
	}

	array := make([]procStatus, 0)
	threadArray := make([]thread, 0)
	summaryArray := make([]procSummary, 0)

	hs, err := syscall.CreateToolhelp32Snapshot(syscall.TH32CS_SNAPPROCESS, 0)
	if err != nil {
		return nil, errors.New("Cannot get process table snapshot")
	}
	defer syscall.CloseHandle(hs)

	var procerr error
	var pe syscall.ProcessEntry32
	pe.Size = uint32(unsafe.Sizeof(pe))
	var pids []uint32

	for procerr = syscall.Process32First(hs, &pe); procerr == nil; procerr = syscall.Process32Next(hs, &pe) {
		procName := windows.UTF16ToString(pe.ExeFile[:])
		if name != "" && procName != name {
			continue
		}
		var uname string
		var sid string
		uname, sid, err = getProcessUsername(pe.ProcessID, true)

		if userName != "" && (err != nil || strings.ToUpper(uname) != userName) {
			continue
		}

		if err != nil {
			uname = "-1"
			sid = "-1"
		}

		proc := procStatus{Pid: pe.ProcessID, PPid: pe.ParentProcessID, Name: procName, Threads: pe.Threads,
			User: uname, Sid: sid}

		// process might not exist anymore already, skipping silently
		h, err := syscall.OpenProcess(windows.PROCESS_QUERY_LIMITED_INFORMATION, false, pe.ProcessID)
		if err != nil {
			continue
		}
		defer syscall.CloseHandle(h)

		if count, err := win32.GetProcessHandleCount(h); err == nil {
			proc.Handles = int64(count)
		} else {
			proc.Handles = -1
		}

		if m, err := win32.GetProcessMemoryInfo(h); err == nil {
			proc.Vmsize = float64(m.PagefileUsage) / 1024
			proc.Wkset = float64(m.WorkingSetSize) / 1024
			proc.PageFaults = int64(m.PageFaultCount)
		} else {
			proc.Vmsize = -1
			proc.Wkset = -1
			proc.PageFaults = -1
		}

		if ioc, err := win32.GetProcessIoCounters(h); err == nil {
			proc.IoReadsB = int64(ioc.ReadTransferCount)
			proc.IoWritesB = int64(ioc.WriteTransferCount)
			proc.IoOtherB = int64(ioc.OtherTransferCount)
			proc.IoReadsOp = int64(ioc.ReadOperationCount)
			proc.IoWritesOp = int64(ioc.WriteOperationCount)
			proc.IoOtherOp = int64(ioc.OtherOperationCount)
		} else {
			proc.IoReadsB = -1
			proc.IoWritesB = -1
			proc.IoOtherB = -1
			proc.IoReadsOp = -1
			proc.IoWritesOp = -1
			proc.IoOtherOp = -1
		}

		var creationTime, exitTime, kernelTime, userTime syscall.Filetime
		if err = syscall.GetProcessTimes(h, &creationTime, &exitTime, &kernelTime, &userTime); err == nil {
			proc.CpuTimeUser = float64(uint64(userTime.HighDateTime)<<32 | uint64(userTime.LowDateTime)) / 1e7
			proc.CpuTimeSystem = float64(uint64(kernelTime.HighDateTime)<<32 | uint64(kernelTime.LowDateTime)) / 1e7
		} else {
			proc.CpuTimeUser = -1
			proc.CpuTimeSystem = -1
		}

		array = append(array, proc)
		pids = append(pids, pe.ProcessID)
	}

	if mode == "thread" {
		ht, err := windows.CreateToolhelp32Snapshot(syscall.TH32CS_SNAPTHREAD, 0)
		if err != nil {
			return nil, errors.New("Cannot get thread table snapshot")
		}

		var te windows.ThreadEntry32
		te.Size = uint32(unsafe.Sizeof(te))
		for procerr = windows.Thread32First(ht, &te); procerr == nil; procerr = windows.Thread32Next(ht, &te) {
			for _, proc := range array {
				if te.OwnerProcessID == proc.Pid {
					threadArray = append(threadArray, thread{proc.Pid, proc.PPid, proc.Name,
						proc.User, proc.Sid, te.ThreadID})
					break
				}
			}
                }
		defer windows.CloseHandle(ht)
	}

	var jsonArray []byte
	switch mode {
	case "summary":
		var processed []string
		processes:
		for i, proc := range array {
			for _, j := range processed {
				if j == proc.Name {
					continue processes
				}
			}

			procSum := procSummary{proc.Name, 1, proc.Vmsize, proc.Wkset,
				proc.CpuTimeUser, proc.CpuTimeSystem, proc.Threads,
				proc.PageFaults, proc.Handles, proc.IoReadsB,
				proc.IoWritesB, proc.IoReadsOp, proc.IoWritesOp,
				proc.IoOtherB, proc.IoOtherOp}

			if len(array) > i + 1 {
				for _, procCmp := range array[i + 1:] {
					if procCmp.Name != proc.Name {
						continue
					}
					procSum.Processes++
					procSum.Threads += procCmp.Threads
					addNonNegativeFloat(&procSum.Vmsize, procCmp.Vmsize)
					addNonNegativeFloat(&procSum.Wkset, procCmp.Wkset)
					addNonNegativeFloat(&procSum.CpuTimeUser, procCmp.CpuTimeUser)
					addNonNegativeFloat(&procSum.CpuTimeSystem, procCmp.CpuTimeSystem)
					addNonNegative(&procSum.PageFaults, procCmp.PageFaults)
					addNonNegative(&procSum.Handles, procCmp.Handles)
					addNonNegative(&procSum.IoReadsB, procCmp.IoReadsB)
					addNonNegative(&procSum.IoWritesB, procCmp.IoWritesB)
					addNonNegative(&procSum.IoOtherB, procCmp.IoOtherB)
					addNonNegative(&procSum.IoReadsOp, procCmp.IoReadsOp)
					addNonNegative(&procSum.IoWritesOp, procCmp.IoWritesOp)
					addNonNegative(&procSum.IoOtherOp, procCmp.IoOtherOp)
				}
			}
			processed = append(processed, proc.Name)
			summaryArray = append(summaryArray, procSum)
		}
		jsonArray, err = json.Marshal(summaryArray)
	case "thread":
		jsonArray, err = json.Marshal(threadArray)
	default:
		jsonArray, err = json.Marshal(array)
	}

	if err != nil {
		return nil, fmt.Errorf("Cannot create JSON array: %s", err)
	}

	return string(jsonArray), nil
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
