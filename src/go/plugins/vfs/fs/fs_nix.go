// +build !windows

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

package vfsfs

import (
	"bufio"
	"fmt"
	"io"
	"os"
	"strings"

	"golang.org/x/sys/unix"
)

func (p *Plugin) getFsInfoStats() (data []*FsInfo, err error) {
	fullData, err := p.getFsInfo()
	if err != nil {
		return nil, err
	}
	fmt.Println("got here")

	for _, info := range fullData {
		bytes, err := getFsStats(*info.FsName)
		if err != nil {
			return nil, err
		}
		if bytes.Total > 0 {
			info.Bytes = bytes
			//TODO: add inode
			data = append(data, info)
		}
	}

	return
}

func (p *Plugin) readMounts(file io.Reader) (data []*FsInfo, err error) {
	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := scanner.Text()
		mnt := strings.Split(line, " ")
		if len(mnt) < 3 {
			p.Debugf(`cannot discern the mount in given line: %s`, line)
			continue
		}
		data = append(data, &FsInfo{FsName: &mnt[1], FsType: &mnt[2]})
	}

	if err = scanner.Err(); err != nil {
		return nil, err
	}

	return
}

func (p *Plugin) getFsInfo() (data []*FsInfo, err error) {
	file, err := os.Open("/proc/mounts")
	if err != nil {
		return nil, err
	}
	defer file.Close()

	data, err = p.readMounts(file)
	if err != nil {
		return nil, err
	}

	return data, nil
}

func getFsStats(path string) (stats *FsStats, err error) {
	fs := unix.Statfs_t{}
	err = unix.Statfs(path, &fs)
	if err != nil {
		return nil, err
	}

	total := fs.Blocks * uint64(fs.Bsize)
	free := fs.Bfree * uint64(fs.Bsize)
	used := total - free
	stats = &FsStats{
		Total: total,
		Free:  free,
		Used:  used,
		PFree: float64(free) / float64(total) * 100,
		PUsed: float64(used) / float64(total) * 100,
	}

	return
}
