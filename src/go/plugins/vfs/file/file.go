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

package file

import (
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/std"
)

var (
	impl  Plugin
	stdOs std.Os
)

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	options Options
}

func init() {
	stdOs = std.NewOs()

	err := plugin.RegisterMetrics(
		&impl, "File",
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
		"vfs.file.get", "Return json object with information about a file.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "vfs.file.cksum":
		return p.exportCksum(params, ctx.Timeout())
	case "vfs.file.contents":
		return p.exportContents(params)
	case "vfs.file.exists":
		return p.exportExists(params)
	case "vfs.file.size":
		return p.exportSize(params)
	case "vfs.file.time":
		return p.exportTime(params)
	case "vfs.file.regexp":
		return p.exportRegexp(params, ctx.Timeout())
	case "vfs.file.regmatch":
		return p.exportRegmatch(params, ctx.Timeout())
	case "vfs.file.md5sum":
		return p.exportMd5sum(params, ctx.Timeout())
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
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}
