//go:build !windows
// +build !windows

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

package vfsfs

import (
	"bufio"
	"io"
	"os"
	"strings"

	"golang.org/x/sys/unix"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

func init() {
	err := plugin.RegisterMetrics(
		&impl, "VfsFs",
		"vfs.fs.discovery", "List of mounted filesystems. Used for low-level discovery.",
		"vfs.fs.get", "List of mounted filesystems with statistics.",
		"vfs.fs.size", "Disk space in bytes or in percentage from total.",
		"vfs.fs.inode", "Disk space in bytes or in percentage from total.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

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

		fsmap[*info.FsName+*info.FsType] = &FsInfoNew{info.FsName, info.FsType, nil, nil, bytes, inodes, info.FsOptions}
	}

	allData, err = p.getFsInfo()
	if err != nil {
		return nil, err
	}

	for _, info := range allData {
		if fsInfo, ok := fsmap[*info.FsName+*info.FsType]; ok {
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
		if len(mnt) < 4 {
			p.Debugf(`cannot discern the mount in given line: %s`, line)
			continue
		}
		data = append(data, &FsInfo{FsName: &mnt[1], FsType: &mnt[2], FsOptions: &mnt[3]})
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
	var pused float64

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
	pfree := float64(fs.Blocks - fs.Bfree + fs.Bavail)

	if pfree > 0 {
		pfree = 100.00 * float64(available) / pfree
		pused = 100 - pfree
	} else {
		pfree = 0
		pused = 0
	}

	stats = &FsStats{
		Total: total,
		Free:  free,
		Used:  used,
		PFree: pfree,
		PUsed: pused,
	}

	return
}

func getFsInode(path string) (stats *FsStats, err error) {
	var pfree, pused float64

	fs := unix.Statfs_t{}
	err = unix.Statfs(path, &fs)
	if err != nil {
		return nil, err
	}

	total := fs.Files
	free := fs.Ffree
	used := fs.Files - fs.Ffree

	if 0 < total {
		pfree = 100 * float64(free) / float64(total)
		pused = 100 * float64(total-free) / float64(total)
	} else {
		pfree = 100.0
		pused = 0.0
	}

	stats = &FsStats{
		Total: total,
		Free:  free,
		Used:  used,
		PFree: pfree,
		PUsed: pused,
	}

	return
}
