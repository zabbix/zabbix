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

package proc

import (
	"bytes"
	"fmt"
	"io/ioutil"
	"os"
	"strconv"
	"strings"
	"syscall"
	"zabbix/pkg/log"
)

func (p *Plugin) getProcessName(pid int64) (name string, err error) {
	var data []byte
	if data, err = ioutil.ReadFile(fmt.Sprintf("/proc/%d/stat", pid)); err != nil {
		return
	}
	var left, right int
	if right = bytes.LastIndexByte(data, ')'); right == -1 {
		return "", fmt.Errorf("cannot find end of process name in /proc/%d/stat", pid)
	}
	if left = bytes.IndexByte(data[:right], '('); left == -1 {
		return "", fmt.Errorf("cannot find start process name in /proc/%d/stat", pid)
	}
	return string(data[left+1 : right]), nil
}

func (p *Plugin) getProcessUserID(pid int64) (userid int64, err error) {
	var fi os.FileInfo
	if fi, err = os.Stat(fmt.Sprintf("/proc/%d", pid)); err != nil {
		return
	}
	return int64(fi.Sys().(*syscall.Stat_t).Uid), nil
}

func (p *Plugin) getProcessCmdline(pid int64) (cmdline []string, err error) {
	var data []byte
	if data, err = ioutil.ReadFile(fmt.Sprintf("/proc/%d/cmdline", pid)); err != nil {
		return
	}
	params := bytes.Split(data, []byte{0})
	cmdline = make([]string, len(params))
	for i := range params {
		cmdline[i] = string(params[i])
	}
	return cmdline, nil
}

func (p *Plugin) getProcCpuUtil(pid int64, stat *cpuUtil) {
	var data []byte
	if data, stat.err = ioutil.ReadFile(fmt.Sprintf("/proc/%d/stat", pid)); stat.err != nil {
		return
	}
	var pos int
	if pos = bytes.LastIndexByte(data, ')'); pos == -1 {
		stat.err = fmt.Errorf("cannot find start of cpu stats in /proc/%d/stat", pid)
		return
	}
	stats := bytes.Split(data[pos+2:], []byte{' '})
	if stat.utime, stat.err = strconv.ParseUint(string(stats[11]), 10, 64); stat.err != nil {
		return
	}
	if stat.stime, stat.err = strconv.ParseUint(string(stats[12]), 10, 64); stat.err != nil {
		return
	}
	if stat.started, stat.err = strconv.ParseUint(string(stats[19]), 10, 64); stat.err != nil {
		return
	}
}

func (p *Plugin) getProcesses(flags int) (processes []*procInfo, err error) {
	var files []os.FileInfo
	if files, err = ioutil.ReadDir("/proc"); err != nil {
		return
	}
	processes = make([]*procInfo, 0, len(files))

	for _, file := range files {
		if !file.IsDir() {
			continue
		}
		var pid int64
		var tmperr error
		if pid, tmperr = strconv.ParseInt(file.Name(), 10, 64); tmperr != nil {
			continue
		}
		info := &procInfo{pid: pid}
		if flags&procInfoName != 0 {
			if info.name, tmperr = p.getProcessName(pid); tmperr != nil {
				log.Debugf("cannot get process %d name: %s", pid, tmperr)
				continue
			}
		}
		if flags&procInfoUser != 0 {
			if info.userid, tmperr = p.getProcessUserID(pid); tmperr != nil {
				log.Debugf("cannot get process %d user id: %s", pid, tmperr)
				continue
			}
		}
		if flags&procInfoCmdline != 0 {
			var params []string
			if params, tmperr = p.getProcessCmdline(pid); tmperr != nil {
				log.Debugf("cannot get process %d command line: %s", pid, tmperr)
				continue
			}
			if flags&procInfoName != 0 && len(params) > 0 {
				if pos := strings.IndexByte(params[0], '/'); pos != -1 {
					info.arg0 = params[0][pos+1:]
				} else {
					info.arg0 = params[0]
				}
			}
			info.cmdline = strings.Join(params, " ")
		}
		processes = append(processes, info)
	}

	return processes, nil
}
