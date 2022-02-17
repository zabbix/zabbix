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

package file

import (
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/std"
)

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              int `conf:"optional,range=1:30"`
	Capacity             int `conf:"optional,range=1:100"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "vfs.file.cksum":
		return p.exportCksum(params)
	case "vfs.file.contents":
		return p.exportContents(params)
	case "vfs.file.exists":
		return p.exportExists(params)
	case "vfs.file.size":
		return p.exportSize(params)
	case "vfs.file.time":
		return p.exportTime(params)
	case "vfs.file.regexp":
		return p.exportRegexp(params)
	case "vfs.file.regmatch":
		return p.exportRegmatch(params)
	case "vfs.file.md5sum":
		return p.exportMd5sum(params)
	case "vfs.file.owner":
		return p.exportOwner(params)
	case "vfs.file.permissions":
		return p.exportPermissions(params)
	case "vfs.file.get":
		return p.exportGet(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

var stdOs std.Os

func init() {
	stdOs = std.NewOs()
	plugin.RegisterMetrics(&impl, "File",
		"vfs.file.cksum", "Returns File checksum, calculated by the UNIX cksum algorithm.",
		"vfs.file.contents", "Retrieves contents of the file.",
		"vfs.file.exists", "Returns if file exists or not.",
		"vfs.file.time", "Returns file time information.",
		"vfs.file.size", "Returns file size.",
		"vfs.file.regexp", "Find string in a file.",
		"vfs.file.regmatch", "Find string in a file.",
		"vfs.file.md5sum", "Returns MD5 checksum of file.",
		"vfs.file.owner", "Returns the ownership of a file.",
		"vfs.file.permissions", "Returns 4-digit string containing octal number with Unix permissions.",
		"vfs.file.get", "Return json object with information about a file.")
}
