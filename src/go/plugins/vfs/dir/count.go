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
	"fmt"
	"io/fs"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxerr"
)

//Plugin -
type Plugin struct {
	plugin.Base
}

type countParams struct {
	path          string
	typesInclude  map[fs.FileMode]bool
	typesExclude  map[fs.FileMode]bool
	maxDepth      int
	minSize       int
	maxSize       int
	minAge        int
	maxAge        int
	regExclude    *regexp.Regexp
	dirRegExclude *regexp.Regexp
	regInclude    *regexp.Regexp
}

var impl Plugin

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "vfs.dir.count":
		return p.exportCount(params)
	default:
		return nil, zbxerr.ErrorUnsupportedMetric
	}

	return nil, nil
}

func (p *Plugin) exportCount(params []string) (result interface{}, err error) {
	cp, err := parseParams(params)
	if err != nil {
		return
	}

	return cp.getDirCount()
}

func (cp countParams) getDirCount() (int, error) {
	var count int
	err := filepath.WalkDir(cp.path,
		func(p string, d fs.DirEntry, err error) error {
			if err != nil {
				return err
			}

			if p == cp.path {
				return nil
			}

			if cp.regInclude != nil && !cp.regInclude.Match([]byte(d.Name())) {
				return nil
			}

			if cp.regExclude != nil && cp.regExclude.Match([]byte(d.Name())) {
				return nil
			}

			if len(cp.typesInclude) > 1 && !cp.typesInclude[d.Type()] {
				return nil
			}

			if len(cp.typesExclude) > 1 && cp.typesExclude[d.Type()] {
				return nil
			}

			count++

			return nil
		})

	return count, err
}

func parseParams(params []string) (out countParams, err error) {
	switch len(params) {
	case 11:
		out.dirRegExclude, err = regexp.Compile(params[10])
		if err != nil {
			err = zbxerr.New("Invalid eleventh parameter.").Wrap(err)
			return
		}
		fallthrough
	case 10:
		out.maxAge, err = strconv.Atoi(params[9])
		if err != nil {
			err = zbxerr.New("Invalid thenth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 9:
		out.minAge, err = strconv.Atoi(params[8])
		if err != nil {
			err = zbxerr.New("Invalid ninth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 8:
		out.maxSize, err = strconv.Atoi(params[7])
		if err != nil {
			err = zbxerr.New("Invalid eighth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 7:
		out.minSize, err = strconv.Atoi(params[6])
		if err != nil {
			err = zbxerr.New("Invalid seventh parameter.").Wrap(err)
			return
		}
		fallthrough
	case 6:
		out.maxDepth, err = strconv.Atoi(params[5])
		if err != nil {
			err = zbxerr.New("Invalid sixth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 5:
		out.typesExclude, err = parseType(params[4])
		if err != nil {
			err = zbxerr.New("Invalid sixth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 4:
		out.typesInclude, err = parseType(params[3])
		if err != nil {
			err = zbxerr.New("Invalid fifth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 3:
		out.regExclude, err = regexp.Compile(params[2])
		if err != nil {
			err = zbxerr.New("Invalid fourth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 2:
		out.regInclude, err = regexp.Compile(params[1])
		if err != nil {
			err = zbxerr.New("Invalid third parameter.").Wrap(err)
			return
		}
		fallthrough
	case 1:
		out.path = params[0]
	case 0:
		err = zbxerr.New("Invalid first parameter.")
		return
	default:
		err = zbxerr.ErrorInvalidParams
		return
	}

	return
}

func parseType(in string) (map[fs.FileMode]bool, error) {
	types := strings.SplitAfter(in, ",")
	out := make(map[fs.FileMode]bool)

	for _, t := range types {
		switch t {
		case "file":
			out[0] = true
		case "dir":
			out[fs.ModeDir] = true
		case "sym":
			out[fs.ModeSymlink] = true
		case "sock":
			out[fs.ModeSocket] = true
		case "bdev":
			out[fs.ModeDevice] = true
		case "cdev":
			out[fs.ModeCharDevice] = true
		case "fifo":
			out[fs.ModeNamedPipe] = true
		case "dev":
			out[fs.ModeDevice] = true
			out[fs.ModeCharDevice] = true
		case "all":
			out[fs.ModeDir] = true
			out[fs.ModeSymlink] = true
			out[fs.ModeSocket] = true
			out[fs.ModeDevice] = true
			out[fs.ModeCharDevice] = true
			out[fs.ModeNamedPipe] = true

			//If all are set no need to iterate further
			return out, nil
		default:
			return nil, fmt.Errorf("invalid type: %s", t)
		}
	}

	return out, nil
}

func init() {
	plugin.RegisterMetrics(&impl, "VFSDir",
		"vfs.dir.count", "Directory entry count.")
}
