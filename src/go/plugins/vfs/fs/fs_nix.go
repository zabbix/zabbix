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
	"errors"

	"golang.org/x/sys/unix"
	"zabbix.com/pkg/plugin"
)

func (p *Plugin) getFsInfoStats() (data []*FsInfo, err error) {
	return nil, errors.New("Unsupported item key.")
}

func (p *Plugin) getFsInfo() (data []*FsInfo, err error) {
	return nil, errors.New("Unsupported item key.")
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

func init() {
	plugin.RegisterMetrics(&impl, "VfsFs",
		"vfs.fs.size", "Disk space in bytes or in percentage from total.",
	)
}
