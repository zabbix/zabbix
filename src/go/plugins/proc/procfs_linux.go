//go:build linux
// +build linux

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

/*
#include <unistd.h>
*/
import "C"

import (
	"bytes"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"

	"zabbix.com/pkg/procfs"
	"git.zabbix.com/ap/plugin-support/log"
)

type processUserInfo struct {
	uid	int64
	gid	int64
}

func read2k(filename string) (data []byte, err error) {
	fd, err := syscall.Open(filename, syscall.O_RDONLY, 0)
	if err != nil {
		return
	}
	var n int
	b := make([]byte, 2048)
	if n, err = syscall.Read(fd, b); err == nil {
		data = b[:n]
	}
	syscall.Close(fd)
	return
}

func getProcessName(pid string) (name string, err error) {
	var data []byte
	if data, err = read2k("/proc/" + pid + "/stat"); err != nil {
		return
	}
	var left, right int
	if right = bytes.LastIndexByte(data, ')'); right == -1 {
		return "", fmt.Errorf("cannot find process name ending position in /proc/%s/stat", pid)
	}
	if left = bytes.IndexByte(data[:right], '('); left == -1 {
		return "", fmt.Errorf("cannot find process name starting position in /proc/%s/stat", pid)
	}
	return string(data[left+1 : right]), nil
}

func parseProcessStatus(pid string, proc *procStatus) (err error) {
	proc.Pid, _ = strconv.ParseUint(pid, 10, 64)

	var data []byte
	if data, err = read2k("/proc/" + pid + "/status"); err != nil {
		return err
	}

	var pos int
	var nonvoluntary int64
	nonvoluntary, proc.Vsize, proc.Rss, proc.Data, proc.Exe, proc.Hwm, proc.Lck, proc.Lib, proc.Peak, proc.Pin,
			proc.Pte, proc.Stk, proc.Swap, proc.Threads, proc.CtxSwitches =
			-1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1
	s := strings.Split(string(data), "\n")
	for _, tmp := range s {
		if pos = strings.IndexRune(tmp, ':'); pos == -1 {
			continue
		}

		k := tmp[:pos]
		v := strings.TrimSpace(tmp[pos+1:])

		switch k {
		case "Name":
			proc.ThreadName = v
		case "State":
			parseStateString(v, &proc.State)
		case "PPid":
			setInt64(v, &proc.Ppid)
		case "VmSize":
			trimUnit(v, &proc.Vsize)
		case "VmRSS":
			trimUnit(v, &proc.Rss)
		case "VmData":
			trimUnit(v, &proc.Data)
		case "VmExe":
			trimUnit(v, &proc.Exe)
		case "VmHWM":
			trimUnit(v, &proc.Hwm)
		case "VmLck":
			trimUnit(v, &proc.Lck)
		case "VmLib":
			trimUnit(v, &proc.Lib)
		case "VmPeak":
			trimUnit(v, &proc.Peak)
		case "VmPin":
			trimUnit(v, &proc.Pin)
		case "VmPTE":
			trimUnit(v, &proc.Pte)
		case "VmStk":
			trimUnit(v, &proc.Stk)
		case "VmSwap":
			trimUnit(v, &proc.Swap)
		case "Threads":
			setInt64(v, &proc.Threads)
		case "Tgid":
			setInt64(v, &proc.Tgid)
		case "voluntary_ctxt_switches":
			if value, tmperr := strconv.ParseInt(v, 10, 64); tmperr == nil {
				proc.CtxSwitches = value
			}
		case "nonvoluntary_ctxt_switches":
			if value, tmperr := strconv.ParseInt(v, 10, 64); tmperr == nil {
				nonvoluntary = value
			}
		}
	}

	addNonNegative(&proc.CtxSwitches, nonvoluntary)

	return nil
}

func parseStateString(val string, state *string) () {
	posLeft := strings.IndexRune(val, '(')
	posRight := strings.IndexRune(val, ')')

	if posLeft == -1 || posRight == -1 {
		*state = val
	} else {
		*state = val[posLeft+1:posRight]
	}
}

func getProcessCalculatedMetrics(pid string, proc *procStatus) () {
	addNonNegative(&proc.Size, proc.Exe)
	addNonNegative(&proc.Size, proc.Data)
	addNonNegative(&proc.Size, proc.Stk)

	mem, err := procfs.GetMemory("MemTotal")
	if err != nil || 0 > proc.Rss {
		proc.Pmem = -1
		return
	}

	proc.Pmem = float64(proc.Rss) / float64(mem) * 100.00
}

func getProcessCpuTimes(pid string, proc *procStatus) () {
	var stat procStat
	getProcessStats(pid, &stat)
	if stat.err != nil {
		proc.CpuTimeUser = -1
		proc.CpuTimeSystem = -1
		proc.PageFaults = -1
		return
	}

	log.Tracef("Calling C function \"sysconf()\"")
	proc.CpuTimeUser = float64(stat.utime) / float64(C.sysconf(C._SC_CLK_TCK))
	log.Tracef("Calling C function \"sysconf()\"")
	proc.CpuTimeSystem = float64(stat.stime) / float64(C.sysconf(C._SC_CLK_TCK))
	proc.PageFaults = stat.pageFaults
}

func getProcessNames(pid string, proc *procStatus) (err error) {
	_, proc.Cmdline, err = getProcessCmdline(pid, 0)

	if err != nil {
		return err
	}

	proc.Name = strings.TrimSuffix(proc.ThreadName, ":")

	f := strings.Fields(proc.Cmdline)
	if len(f) > 0 {
		baseName := strings.TrimSuffix(filepath.Base(f[0]), ":")
		if len(baseName) > len(proc.ThreadName) && strings.HasPrefix(baseName, proc.ThreadName) {
			proc.Name = baseName
		}
	}

	return nil
}

func getProcessState(pid string) (name string, err error) {
	var data []byte
	if data, err = read2k("/proc/" + pid + "/status"); err != nil {
		return
	}

	s := strings.Split(string(data), "\n")
	for _, tmp := range s {
		if strings.HasPrefix(tmp, "State:") && len(tmp) > 7 {
			return string(tmp[7:8]), nil
		}
	}

	return "", fmt.Errorf("cannot find process state /proc/%s/status", pid)
}

func getProcessUserInfo(pid string) (userinfo processUserInfo, err error) {
	var fi os.FileInfo
	userinfo = processUserInfo{}
	if fi, err = os.Stat("/proc/" + pid); err != nil {
		return
	}
	stat := fi.Sys().(*syscall.Stat_t)
	userinfo.uid = int64(stat.Uid)
	userinfo.gid = int64(stat.Gid)

	return userinfo, nil
}

func getProcessCmdline(pid string, flags int) (arg0 string, cmdline string, err error) {
	var data []byte
	if data, err = procfs.ReadAll("/proc/" + pid + "/cmdline"); err != nil {
		return
	}

	if flags&procInfoName != 0 {
		if end := bytes.IndexByte(data, 0); end != -1 {
			if pos := bytes.LastIndexByte(data[:end], '/'); pos != -1 {
				arg0 = string(data[pos+1 : end])
			} else {
				arg0 = string(data[:end])
			}
		} else {
			arg0 = string(data)
		}
	}

	for i := 0; i < len(data); i++ {
		if data[i] == 0 {
			data[i] = ' '
		}
	}

	if len(data) != 0 && data[len(data)-1] == ' ' {
		data = data[:len(data)-1]
	}

	return arg0, string(data), nil
}

func getProcessStats(pid string, stat *procStat) {
	var data []byte
	if data, stat.err = read2k("/proc/" + pid + "/stat"); stat.err != nil {
		return
	}
	var pos int
	if pos = bytes.LastIndexByte(data, ')'); pos == -1 || len(data[pos:]) < 2 {
		stat.err = fmt.Errorf("cannot find CPU statistic starting position in /proc/%s/stat", pid)
		return
	}
	stats := bytes.Split(data[pos+2:], []byte{' '})
	if len(stats) < 20 {
		stat.err = fmt.Errorf("cannot parse CPU statistics in /proc/%s/stat", pid)
		return
	}
	if stat.utime, stat.err = strconv.ParseUint(string(stats[11]), 10, 64); stat.err != nil {
		return
	}
	if stat.stime, stat.err = strconv.ParseUint(string(stats[12]), 10, 64); stat.err != nil {
		return
	}
	if stat.started, stat.err = strconv.ParseUint(string(stats[19]), 10, 64); stat.err != nil {
		return
	}
	if stat.pageFaults, stat.err = strconv.ParseInt(string(stats[9]), 10, 64); stat.err != nil {
		return
	}
}

func getProcesses(flags int) (processes []*procInfo, err error) {
	var entries []os.DirEntry
	f, err := os.Open("/proc")
	if err != nil {
		return nil, err
	}
	defer f.Close()

	for entries, err = f.ReadDir(1); err != io.EOF; entries, err = f.ReadDir(1) {
		if err != nil {
			return nil, err
		}

		if len(entries) < 1 || !entries[0].IsDir() {
			continue
		}

		var pid int64
		var tmperr error
		if pid, tmperr = strconv.ParseInt(entries[0].Name(), 10, 64); tmperr != nil {
			continue
		}
		info := &procInfo{pid: pid}
		if flags&procInfoName != 0 {
			if info.name, tmperr = getProcessName(entries[0].Name()); tmperr != nil {
				impl.Debugf("cannot get process %s name: %s", entries[0].Name(), tmperr)
				continue
			}
		}
		if flags&procInfoUser != 0 {
			var pu processUserInfo
			pu, tmperr = getProcessUserInfo(entries[0].Name())
			if tmperr != nil {
				impl.Debugf("cannot get process %s user id: %s", entries[0].Name(), tmperr)
				continue
			}
			info.userid = pu.uid
		}
		if flags&procInfoCmdline != 0 {
			if info.arg0, info.cmdline, tmperr = getProcessCmdline(entries[0].Name(), flags); tmperr != nil {
				impl.Debugf("cannot get process %s command line: %s", entries[0].Name(), tmperr)
				continue
			}
		}
		if flags&procInfoState != 0 {
			if info.state, tmperr = getProcessState(entries[0].Name()); tmperr != nil {
				impl.Debugf("cannot get process %s state: %s", entries[0].Name(), tmperr)
				continue
			}
		}

		processes = append(processes, info)
	}

	return processes, nil
}

func getProcfsIds(path string) (pids []string, err error) {
	dir, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer dir.Close()

	names, err := dir.Readdirnames(0)
	if err != nil {
		return nil, err
	}

	for _, name := range names {
		_, err := strconv.ParseUint(name, 10, 64)
		if err == nil {
			pids = append(pids, name)
		}
	}

	return pids, nil
}

func getPids() (pids []string, err error) {
	return getProcfsIds("/proc")
}

func getThreadIds(pid string) (pids []string, err error) {
	return getProcfsIds("/proc/" + pid + "/task")
}

func trimUnit(v string, p *int64) () {
	var tmperr error
	var value int64
	var pos int

	*p = -1
	if pos = strings.IndexRune(v, ' '); pos == -1 {
		return
	}

	if value, tmperr = strconv.ParseInt(v[:pos], 10, 64); tmperr != nil {
		return
	}

	unit := v[pos + 1:]
	switch unit {
	case "kB":
		value <<= 10
	case "mB":
		value <<= 20
	case "GB":
		value <<= 30
	case "TB":
		value <<= 40
	default:
		return
	}

	*p = value
}

func setInt64(v string, p *int64) () {
	var tmperr error
	var value int64

	if value, tmperr = strconv.ParseInt(v, 10, 64); tmperr != nil {
		*p = -1
		return
	}

	*p = value
}
