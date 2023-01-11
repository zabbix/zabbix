/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

package dir

import (
	"io/fs"
	"path/filepath"
	"regexp"
	"strings"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxerr"
)

var impl Plugin

type common struct {
	path          string
	maxDepth      int
	length        int
	regExclude    *regexp.Regexp
	regInclude    *regexp.Regexp
	dirRegExclude *regexp.Regexp
	files         []fs.FileInfo
}

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "vfs.dir.count":
		return p.exportCount(params)
	case "vfs.dir.size":
		return p.exportSize(params)
	default:
		return nil, zbxerr.ErrorUnsupportedMetric
	}
}

func (p *Plugin) exportCount(params []string) (result interface{}, err error) {
	cp, err := getCountParams(params)
	if err != nil {
		return
	}

	cp.length = len(strings.SplitAfter(cp.path, string(filepath.Separator)))

	err = cp.setMinMax()
	if err != nil {
		return 0, zbxerr.ErrorInvalidParams.Wrap(err)
	}

	return cp.getDirCount()
}

func (p *Plugin) exportSize(params []string) (result interface{}, err error) {
	sp, err := getSizeParams(params)
	if err != nil {
		return
	}

	sp.length = len(strings.SplitAfter(sp.path, string(filepath.Separator)))

	return sp.getDirSize()
}

func (cp *common) skipPath(path string) (bool, error) {
	currentLength := len(strings.SplitAfter(path, string(filepath.Separator)))
	if cp.maxDepth > unlimitedDepth && currentLength-cp.length > cp.maxDepth {
		return true, fs.SkipDir
	}

	return false, nil
}

func (cp *common) skipRegex(d fs.DirEntry) (bool, error) {
	if cp.regInclude != nil && !cp.regInclude.Match([]byte(d.Name())) {
		return true, nil
	}

	if cp.regExclude != nil && cp.regExclude.Match([]byte(d.Name())) {
		return true, nil
	}

	if cp.dirRegExclude != nil && d.IsDir() && cp.dirRegExclude.Match([]byte(d.Name())) {
		return true, fs.SkipDir
	}

	return false, nil
}

func parseReg(in string) (*regexp.Regexp, error) {
	if in == "" {
		return nil, nil
	}

	return regexp.Compile(in)
}

func init() {
	plugin.RegisterMetrics(&impl, "VFSDir",
		"vfs.dir.count", "Directory entry count.",
		"vfs.dir.size", "All directory entry size.",
	)
}
