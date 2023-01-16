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
	"encoding/json"
	"errors"

	"git.zabbix.com/ap/plugin-support/plugin"
)

const (
	errorInvalidParameters = "Invalid number of parameters."
)

const (
	statModeTotal = iota
	statModeFree
	statModeUsed
	statModePFree
	statModePUsed
)

type FsStats struct {
	Total uint64  `json:"total"`
	Free  uint64  `json:"free"`
	Used  uint64  `json:"used"`
	PFree float64 `json:"pfree"`
	PUsed float64 `json:"pused"`
}

type FsInfo struct {
	FsName     *string  `json:"{#FSNAME},omitempty"`
	FsType     *string  `json:"{#FSTYPE},omitempty"`
	DriveLabel *string  `json:"{#FSLABEL},omitempty"`
	DriveType  *string  `json:"{#FSDRIVETYPE},omitempty"`
	Bytes      *FsStats `json:"bytes,omitempty"`
	Inodes     *FsStats `json:"inodes,omitempty"`
}

type FsInfoNew struct {
	FsName     *string  `json:"fsname,omitempty"`
	FsType     *string  `json:"fstype,omitempty"`
	DriveLabel *string  `json:"fslabel,omitempty"`
	DriveType  *string  `json:"fsdrivetype,omitempty"`
	Bytes      *FsStats `json:"bytes,omitempty"`
	Inodes     *FsStats `json:"inodes,omitempty"`
}

type Plugin struct {
	plugin.Base
}

var impl Plugin

func (p *Plugin) exportDiscovery(params []string) (value interface{}, err error) {
	if len(params) != 0 {
		return nil, errors.New(errorInvalidParameters)
	}
	var d []*FsInfo
	if d, err = p.getFsInfo(); err != nil {
		return
	}
	var b []byte
	if b, err = json.Marshal(&d); err != nil {
		return
	}
	return string(b), nil
}

func (p *Plugin) exportGet(params []string) (value interface{}, err error) {
	if len(params) != 0 {
		return nil, errors.New(errorInvalidParameters)
	}
	var d []*FsInfoNew
	if d, err = p.getFsInfoStats(); err != nil {
		return
	}
	var b []byte
	if b, err = json.Marshal(&d); err != nil {
		return
	}
	return string(b), nil
}

func (p *Plugin) export(params []string, getStats func(string) (*FsStats, error)) (value interface{}, err error) {
	if len(params) < 1 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}
	if len(params) > 2 {
		return nil, errors.New("Too many parameters.")
	}
	mode := statModeTotal
	if len(params) == 2 {
		switch params[1] {
		case "total":
		case "free":
			mode = statModeFree
		case "used":
			mode = statModeUsed
		case "pfree":
			mode = statModePFree
		case "pused":
			mode = statModePUsed
		default:
			return nil, errors.New("Invalid second parameter.")
		}
	}

	fsCaller := p.newFSCaller(getStats, 1)

	var stats *FsStats
	if stats, err = fsCaller.run(params[0]); err != nil {
		return
	}

	switch mode {
	case statModeTotal:
		return stats.Total, nil
	case statModeFree:
		return stats.Free, nil
	case statModeUsed:
		return stats.Used, nil
	case statModePFree:
		return stats.PFree, nil
	case statModePUsed:
		return stats.PUsed, nil
	}

	return nil, errors.New("Invalid second parameter.")
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "vfs.fs.discovery":
		return p.exportDiscovery(params)
	case "vfs.fs.get":
		return p.exportGet(params)
	case "vfs.fs.size":
		return p.export(params, getFsStats)
	case "vfs.fs.inode":
		return p.export(params, getFsInode)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}
