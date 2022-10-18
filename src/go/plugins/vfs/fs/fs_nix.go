//go:build !windows
// +build !windows

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

package vfsfs

import (
	"bufio"
	"io"
	"os"
	"strings"

	"git.zabbix.com/ap/plugin-support/plugin"
	"golang.org/x/sys/unix"
)

func (p *Plugin) getFsInfoStats() (data []*FsInfoNew, err error) {
	allData, err := p.getFsInfo()
	if err != nil {
		return nil, err
	}

	fsmap := make(map[string]*FsInfoNew)
	fsStatCaller := p.newFSCaller(getFsStats, len(allData))
	fsInodeCaller := p.newFSCaller(getFsInode, len(allData))

	for _, info := range allData {
		bytes, err := fsStatCaller.run(*info.FsName)
		if err != nil {
			p.Debugf(`cannot discern stats for the mount %s: %s`, *info.FsName, err.Error())
			continue
		}

		inodes, err := fsInodeCaller.run(*info.FsName)
		if err != nil {
			p.Debugf(`cannot discern inode for the mount %s: %s`, *info.FsName, err.Error())
			continue
		}

		if bytes.Total > 0 && inodes.Total > 0 {
			fsmap[*info.FsName] = &FsInfoNew{info.FsName, info.FsType, nil, nil, bytes, inodes}
		}
	}

	allData, err = p.getFsInfo()
	if err != nil {
		return nil, err
	}

	for _, info := range allData {
		if fsInfo, ok := fsmap[*info.FsName]; ok {
			data = append(data, fsInfo)
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

	var available uint64
	if fs.Bavail > 0 {
		available = fs.Bavail
	}

	total := fs.Blocks * uint64(fs.Bsize)
	free := available * uint64(fs.Bsize)
	used := (fs.Blocks - fs.Bfree) * uint64(fs.Bsize)
	pfree := 100.00 * float64(available) / float64(fs.Blocks-fs.Bfree+fs.Bavail)
	stats = &FsStats{
		Total: total,
		Free:  free,
		Used:  used,
		PFree: pfree,
		PUsed: 100 - pfree,
	}

	return
}

func getFsInode(path string) (stats *FsStats, err error) {
	fs := unix.Statfs_t{}
	err = unix.Statfs(path, &fs)
	if err != nil {
		return nil, err
	}

	total := fs.Files
	free := fs.Ffree
	used := fs.Files - fs.Ffree
	stats = &FsStats{
		Total: total,
		Free:  free,
		Used:  used,
		PFree: 100 * float64(free) / float64(total),
		PUsed: 100 * float64(total-free) / float64(total),
	}

	return
}

func init() {
	plugin.RegisterMetrics(&impl, "VfsFs",
		"vfs.fs.discovery", "List of mounted filesystems. Used for low-level discovery.",
		"vfs.fs.get", "List of mounted filesystems with statistics.",
		"vfs.fs.size", "Disk space in bytes or in percentage from total.",
		"vfs.fs.inode", "Disk space in bytes or in percentage from total.",
	)
}
