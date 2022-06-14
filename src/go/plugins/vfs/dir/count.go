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
	"strconv"
	"strings"
	"time"

	"git.zabbix.com/ap/plugin-support/plugin"

	"git.zabbix.com/ap/plugin-support/zbxerr"
)

const (
	emptyParam = iota
	firstParam
	secondParam
	thirdParam
	fourthParam
	fifthParam
	sixthParam
	seventhParam
	eightParam
	ninthParam
	tenthParam
	eleventhParam

	regularFile = 0

	unlimitedDepth = -1

	kilobyteType = 'K'
	megabyteType = 'M'
	gigabyteType = 'G'
	terabyteType = 'T'

	kb = 1024
	mb = kb * 1024
	gb = mb * 1024
	tb = gb * 1024

	secondsType = 's'
	minuteType  = 'm'
	hourType    = 'h'
	dayType     = 'd'
	weekType    = 'w'

	dayMultiplier  = 24
	weekMultiplier = 7
)

//Plugin -
type Plugin struct {
	plugin.Base
}

type countParams struct {
	common
	minSize       string
	maxSize       string
	minAge        string
	maxAge        string
	parsedMinSize int64
	parsedMaxSize int64
	parsedMinAge  time.Time
	parsedMaxAge  time.Time
	typesInclude  map[fs.FileMode]bool
	typesExclude  map[fs.FileMode]bool
}

func (cp *countParams) getDirCount() (int, error) {
	var count int

	err := filepath.WalkDir(cp.path,
		func(p string, d fs.DirEntry, err error) error {
			if err != nil {
				impl.Logger.Errf("failed to walk dir with path  %s", p)
				return nil
			}

			if p == cp.path {
				return nil
			}

			s, err := cp.skip(p, d)
			if s {
				return err
			}

			count++

			return nil
		})

	if err != nil {
		return 0, zbxerr.ErrorCannotParseResult.Wrap(err)
	}

	return count, nil
}

func (cp *countParams) skip(path string, d fs.DirEntry) (bool, error) {
	var s bool

	s, err := cp.skipPath(path)
	if s {
		return true, err
	}

	s, err = cp.skipRegex(d)
	if s {
		return true, err
	}

	s, err = cp.skipInfo(d)
	if s {
		if err != nil {
			impl.Logger.Errf("failed to get file info for path %s, %s", path, err.Error())
		}

		return true, nil
	}

	if cp.skipType(path, d) {
		return true, nil
	}

	return false, nil
}

func (cp *countParams) skipInfo(d fs.DirEntry) (bool, error) {
	i, err := d.Info()
	if err != nil {
		return true, err
	}

	if cp.minSize != "" {
		if cp.parsedMinSize > i.Size() {
			return true, nil
		}
	}

	if cp.maxSize != "" {
		if cp.parsedMaxSize < i.Size() {
			return true, nil
		}
	}

	if cp.minAge != "" && i.ModTime().After(cp.parsedMinAge) {
		return true, nil
	}

	if cp.maxAge != "" && i.ModTime().Before(cp.parsedMaxAge) {
		return true, nil
	}

	return false, nil
}

func (cp *countParams) setMinMax() (err error) {
	err = cp.setMinParams()
	if err != nil {
		return
	}

	err = cp.setMaxParams()
	if err != nil {
		return
	}

	return
}

func (cp *countParams) setMaxParams() (err error) {
	cp.parsedMaxSize, err = parseByte(cp.maxSize)
	if err != nil {
		return
	}

	if cp.maxAge != "" {
		var age time.Duration
		age, err = parseTime(cp.maxAge)
		if err != nil {
			return
		}

		cp.parsedMaxAge = time.Now().Add(-age)
	}

	return
}

func (cp *countParams) setMinParams() (err error) {
	cp.parsedMinSize, err = parseByte(cp.minSize)
	if err != nil {
		err = zbxerr.ErrorInvalidParams.Wrap(err)

		return
	}

	if cp.minAge != "" {
		var age time.Duration
		age, err = parseTime(cp.minAge)
		if err != nil {
			return
		}

		cp.parsedMinAge = time.Now().Add(-age)
	}

	return
}

func isTypeMatch(in map[fs.FileMode]bool, fm fs.FileMode) bool {
	if in[regularFile] && fm.IsRegular() {
		return true
	}

	if in[fm.Type()] {
		return true
	}

	return false
}

func getCountParams(params []string) (out countParams, err error) {
	out.maxDepth = -1

	switch len(params) {
	case eleventhParam:
		out.dirRegExclude, err = parseReg(params[10])
		if err != nil {
			err = zbxerr.New("Invalid eleventh parameter.").Wrap(err)

			return
		}

		fallthrough
	case tenthParam:
		out.maxAge = params[9]

		fallthrough
	case ninthParam:
		out.minAge = params[8]

		fallthrough
	case eightParam:
		out.maxSize = params[7]

		fallthrough
	case seventhParam:
		out.minSize = params[6]

		fallthrough
	case sixthParam:
		if params[5] != "" {
			out.maxDepth, err = strconv.Atoi(params[5])
			if err != nil {
				err = zbxerr.New("Invalid sixth parameter.").Wrap(err)

				return
			}

			if out.maxDepth < unlimitedDepth {
				err = zbxerr.New("Invalid sixth parameter.")

				return
			}
		}

		fallthrough
	case fifthParam:
		out.typesExclude, err = parseType(params[4], true)
		if err != nil {
			err = zbxerr.New("Invalid fifth parameter.").Wrap(err)

			return
		}

		fallthrough
	case fourthParam:
		out.typesInclude, err = parseType(params[3], false)
		if err != nil {
			err = zbxerr.New("Invalid fourth parameter.").Wrap(err)

			return
		}

		fallthrough
	case thirdParam:
		out.regExclude, err = parseReg(params[2])
		if err != nil {
			err = zbxerr.New("Invalid third parameter.").Wrap(err)

			return
		}

		fallthrough
	case secondParam:
		out.regInclude, err = parseReg(params[1])
		if err != nil {
			err = zbxerr.New("Invalid second parameter.").Wrap(err)

			return
		}

		fallthrough
	case firstParam:
		out.path = params[0]
		if out.path == "" {
			err = zbxerr.New("Invalid first parameter.")

			return
		}

		if !strings.HasSuffix(out.path, string(filepath.Separator)) {
			out.path += string(filepath.Separator)
		}

	case emptyParam:
		err = zbxerr.ErrorTooFewParameters

		return
	default:
		err = zbxerr.ErrorTooManyParameters

		return
	}

	return
}

func parseType(in string, exclude bool) (out map[fs.FileMode]bool, err error) {
	if in == "" {
		return getEmptyType(exclude), nil
	}

	out = make(map[fs.FileMode]bool)
	types := strings.Split(in, ",")

	for _, t := range types {
		t = strings.TrimSpace(t)

		switch t {
		case "all":
			//If all are set no need to iterate further
			return getAllMode(), nil
		default:
			out, err = setIndividualType(out, t)
			if err != nil {
				return nil, err
			}
		}
	}

	return out, nil
}

func setIndividualType(m map[fs.FileMode]bool, t string) (map[fs.FileMode]bool, error) {
	switch t {
	case "file":
		m[regularFile] = true
	case "dir":
		m[fs.ModeDir] = true
	case "sym":
		m[fs.ModeSymlink] = true
	case "sock":
		m[fs.ModeSocket] = true
	case "bdev":
		m[fs.ModeDevice] = true
	case "cdev":
		m[fs.ModeDevice+fs.ModeCharDevice] = true
	case "fifo":
		m[fs.ModeNamedPipe] = true
	case "dev":
		m[fs.ModeDevice] = true
		m[fs.ModeDevice+fs.ModeCharDevice] = true
	default:
		return nil, fmt.Errorf("invalid type: %s", t)
	}

	return m, nil
}

func getEmptyType(exclude bool) map[fs.FileMode]bool {
	if exclude {
		return nil
	}

	return getAllMode()
}

func getAllMode() map[fs.FileMode]bool {
	out := make(map[fs.FileMode]bool)
	out[regularFile] = true
	out[fs.ModeDir] = true
	out[fs.ModeSymlink] = true
	out[fs.ModeSocket] = true
	out[fs.ModeDevice] = true
	out[fs.ModeCharDevice+fs.ModeDevice] = true
	out[fs.ModeNamedPipe] = true

	return out
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
