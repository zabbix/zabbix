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
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"zabbix.com/pkg/zbxerr"
)

const (
	diskBlockSize = 512
)

type sizeParams struct {
	common
	diskMode bool
	disk     string
}

func (sp *sizeParams) getDirSize() (int64, error) {
	var parentSize int64
	var dirSize int64

	err := filepath.WalkDir(sp.path,
		func(p string, d fs.DirEntry, err error) error {
			if err != nil {
				impl.Logger.Errf("failed to walk dir with path  %s", p)
				return nil
			}

			if p == sp.path {
				parentSize, err = sp.handleHomeDir(p, d)
				if err != nil {
					return err
				}

				return nil
			}

			s, err := sp.skip(p, d)
			if s {
				return err
			}

			fi, err := d.Info()
			if err != nil {
				impl.Logger.Errf("failed to get file info for path %s, %s", p, err.Error())
				return nil
			}

			tmpSize, err := sp.getSize(fi, p)
			if err != nil {
				return err
			}

			dirSize += tmpSize

			return nil
		})

	if err != nil {
		return 0, zbxerr.ErrorCannotParseResult.Wrap(err)
	}

	return parentSize + dirSize, nil
}

func (sp *sizeParams) getParentSize(dir fs.DirEntry) (int64, error) {
	var parentSize int64

	stat, err := os.Stat(sp.path)
	if err != nil {
		return 0, err
	}

	s, err := sp.skipRegex(dir)
	if err != nil {
		return 0, err
	}

	if s {
		return 0, nil
	}

	parentSize, err = sp.getSize(stat, sp.path)
	if err != nil {
		return 0, err
	}

	return parentSize, nil
}

func (sp *sizeParams) skip(path string, d fs.DirEntry) (bool, error) {
	s, err := sp.skipPath(path)
	if s {
		return true, err
	}

	s, err = sp.skipRegex(d)
	if s {
		return true, err
	}

	if skipDir(d) {
		return true, nil
	}

	if sp.osSkip(path, d) {
		return true, nil
	}

	return false, nil
}

func getSizeParams(params []string) (out sizeParams, err error) {
	out.maxDepth = -1

	switch len(params) {
	case sixthParam:
		out.dirRegExclude, err = parseReg(params[5])
		if err != nil {
			err = zbxerr.New("Invalid sixth parameter.").Wrap(err)

			return
		}

		fallthrough
	case fifthParam:
		if params[4] != "" {
			out.maxDepth, err = strconv.Atoi(params[4])
			if err != nil {
				err = zbxerr.New("Invalid fifth parameter.").Wrap(err)

				return
			}

			if out.maxDepth < unlimitedDepth {
				err = zbxerr.New("Invalid fifth parameter.")

				return
			}
		}

		fallthrough
	case fourthParam:
		switch params[3] {
		case "apparent", "":
		case "disk":
			out.diskMode = true
		default:
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
