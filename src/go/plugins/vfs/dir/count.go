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
	"time"

	"zabbix.com/pkg/plugin"

	"zabbix.com/pkg/zbxerr"
)

const (
	modeFile = 0

	kilobyteType = 'K'
	megabyteType = 'M'
	gigabyteType = 'G'
	terabyteType = 'T'

	kb = int64(1000)
	mb = kb * 1000
	gb = mb * 1000
	tb = gb * 1000

	secondsType = 's'
	minuteType  = 'm'
	hourType    = 'h'
	dayType     = 'd'
	weekType    = 'w'

	dayMultiplier  = time.Duration(24)
	weekMultiplier = time.Duration(7)
)

//Plugin -
type Plugin struct {
	plugin.Base
}

type countParams struct {
	path          string
	minSize       string
	maxSize       string
	minAge        string
	maxAge        string
	maxDepth      int
	typesInclude  map[fs.FileMode]bool
	typesExclude  map[fs.FileMode]bool
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

	ogLength := len(strings.SplitAfter(cp.path, string(filepath.Separator)))

	var minSize, maxSize int64
	var minAge, maxAge time.Time

	var err error

	if cp.minSize != "" {
		minSize, err = parseByte(cp.minSize)
		if err != nil {
			return 0, zbxerr.ErrorInvalidParams.Wrap(err)
		}
	}

	if cp.maxSize != "" {
		maxSize, err = parseByte(cp.maxSize)
		if err != nil {
			return 0, zbxerr.ErrorInvalidParams.Wrap(err)
		}
	}

	if cp.minAge != "" {
		age, err := parseTime(cp.minAge)
		if err != nil {
			return 0, zbxerr.ErrorInvalidParams.Wrap(err)
		}

		minAge = time.Now().Add(-age)
	}

	if cp.maxAge != "" {
		age, err := parseTime(cp.maxAge)
		if err != nil {
			return 0, zbxerr.ErrorInvalidParams.Wrap(err)
		}

		maxAge = time.Now().Add(-age)
	}

	err = filepath.WalkDir(cp.path,
		func(p string, d fs.DirEntry, err error) error {
			if err != nil {
				return err
			}

			if p == cp.path {
				return nil
			}

			length := len(strings.SplitAfter(p, string(filepath.Separator)))
			if cp.maxDepth > 0 && length-ogLength > cp.maxDepth {
				return fs.SkipDir
			}

			if cp.regInclude != nil && !cp.regInclude.Match([]byte(d.Name())) {
				return nil
			}

			if cp.regExclude != nil && cp.regExclude.Match([]byte(d.Name())) {
				return nil
			}

			if cp.dirRegExclude != nil && d.IsDir() && cp.dirRegExclude.Match([]byte(d.Name())) {
				return fs.SkipDir
			}

			if len(cp.typesInclude) > 1 && !cp.typesInclude[d.Type()] {
				return nil
			}

			if len(cp.typesExclude) > 1 && cp.typesExclude[d.Type()] {
				return nil
			}

			i, err := d.Info()
			if err != nil {
				return err
			}

			if cp.minSize != "" {
				if minSize > i.Size() {
					return nil
				}
			}

			if cp.maxSize != "" {
				if maxSize < i.Size() {
					return nil
				}
			}

			if !i.ModTime().After(minAge) {
				return nil
			}

			if i.ModTime().Before(maxAge) {
				return nil
			}

			count++

			return nil
		})

	if err != nil {
		return 0, zbxerr.ErrorCannotParseResult.Wrap(err)
	}
	return count, nil
}

func parseParams(params []string) (out countParams, err error) {
	out.maxDepth = -1

	switch len(params) {
	case 11:
		out.dirRegExclude, err = parseReg(params[10])
		if err != nil {
			err = zbxerr.New("Invalid eleventh parameter.").Wrap(err)
			return
		}
		fallthrough
	case 10:
		out.maxAge = params[9]

		fallthrough
	case 9:
		out.minAge = params[8]

		fallthrough
	case 8:
		out.maxSize = params[7]
		fallthrough
	case 7:
		out.minSize = params[6]
		fallthrough
	case 6:
		if params[5] != "" {
			out.maxDepth, err = strconv.Atoi(string(params[5]))
			if err != nil {
				err = zbxerr.New("Invalid sixth parameter.").Wrap(err)
				return
			}
		}

		fallthrough
	case 5:
		out.typesExclude, err = parseType(params[4], true)
		if err != nil {
			err = zbxerr.New("Invalid fifth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 4:
		out.typesInclude, err = parseType(params[3], false)
		if err != nil {
			err = zbxerr.New("Invalid fourth parameter.").Wrap(err)
			return
		}
		fallthrough
	case 3:
		out.regExclude, err = parseReg(params[2])
		if err != nil {
			err = zbxerr.New("Invalid third parameter.").Wrap(err)
			return
		}
		fallthrough
	case 2:
		out.regInclude, err = parseReg(params[1])
		if err != nil {
			err = zbxerr.New("Invalid second parameter.").Wrap(err)
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

func parseReg(in string) (*regexp.Regexp, error) {
	if in == "" {
		return nil, nil
	}

	return regexp.Compile(in)
}

func parseType(in string, exclude bool) (map[fs.FileMode]bool, error) {
	types := strings.SplitAfter(in, ",")
	out := make(map[fs.FileMode]bool)

	if in == "" {
		switch exclude {
		case true:
			return nil, nil
		case false:
			out[modeFile] = true
			out[fs.ModeDir] = true
			out[fs.ModeSymlink] = true
			out[fs.ModeSocket] = true
			out[fs.ModeDevice] = true
			out[fs.ModeCharDevice] = true
			out[fs.ModeNamedPipe] = true

			return out, nil
		}
	}

	for _, t := range types {
		switch t {
		case "file":
			out[modeFile] = true
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
			out[modeFile] = true
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

func parseByte(in string) (int64, error) {
	if in == "" {
		return 0, nil
	}

	bytes, err := strconv.ParseInt(in, 10, 64)
	if err != nil {
		bytes, err := strconv.ParseInt(in[:len(in)-1], 10, 64)
		if err != nil {
			return 0, err
		}

		suffix := in[len(in)-1]
		switch suffix {
		case kilobyteType:
			return bytes * kb, nil
		case megabyteType:
			return bytes * mb, nil
		case gigabyteType:
			return bytes * gb, nil
		case terabyteType:
			return bytes * tb, nil
		default:
			return 0, fmt.Errorf("unknown memory suffix %s", string(suffix))
		}
	}

	return bytes, nil
}

func parseTime(in string) (time.Duration, error) {
	if in == "" {
		return 0 * time.Second, nil
	}

	t, err := strconv.ParseInt(in, 10, 64)
	if err != nil {
		t, err := strconv.ParseInt(in[:len(in)-1], 10, 64)
		if err != nil {
			return 0, err
		}

		suffix := in[len(in)-1]
		switch suffix {
		case secondsType:
			return time.Duration(t) * time.Second, nil
		case minuteType:
			return time.Duration(t) * time.Minute, nil
		case hourType:
			return time.Duration(t) * time.Hour, nil
		case dayType:
			return time.Duration(t) * time.Hour * dayMultiplier, nil
		case weekType:
			return time.Duration(t) * time.Hour * dayMultiplier * weekMultiplier, nil
		default:
			return 0, fmt.Errorf("unknown time suffix %s", string(suffix))
		}
	}

	return time.Duration(t) * time.Second, nil
}

func init() {
	plugin.RegisterMetrics(&impl, "VFSDir",
		"vfs.dir.count", "Directory entry count.")
}
