/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	"errors"
	"fmt"
	"os"
	"strings"
)

// File Types
const (
	zbxFtFile = 1 << iota
	zbxFtDir
	zbxFtSym
	zbxFtSock
	zbxFtBdev
	zbxFtCdev
	zbxFtFifo
	zbxFtAll
	zbxFtDev
	zbxFtAllMask = (zbxFtFile | zbxFtDir | zbxFtSym | zbxFtSock | zbxFtBdev | zbxFtCdev | zbxFtFifo)
	zbxFtDev2    = (zbxFtBdev | zbxFtCdev)
)

type fileType uint16

func (f fileType) HasType(t fileType) bool { return f&t != 0 }
func (f *fileType) AddType(t fileType)     { *f |= t }
func (f *fileType) ClearType(t fileType)   { *f &= ^t }

func typesToMask(types []string) (fileType, error) {
	var mask fileType = 0
	var fType fileType
	template := [9]string{"file", "dir", "sym", "sock", "bdev", "cdev", "fifo", "all", "dev"}

	if len(types) == 0 || types[0] == "" {
		return 0, nil
	}

	for i := 0; i < len(types); i++ {
		fType = 1

		for j := 0; j <= len(template); j++ {

			if j == len(template) {
				return 0, fmt.Errorf("Invalid type \"%s\".", types[i])
			}

			if template[j] == types[i] {
				break
			}

			fType <<= 1
		}

		mask.AddType(fType)
	}

	if mask.HasType(zbxFtAll) {
		mask.AddType(zbxFtAllMask)
	}

	if mask.HasType(zbxFtDev) {
		mask.AddType(zbxFtDev2)
	}

	return mask, nil
}

// Export -
func (p *Plugin) exportExists(params []string) (result interface{}, err error) {
	var typesIncl fileType
	var typesExcl fileType

	if len(params) > 3 {
		return nil, errors.New("Too many parameters.")
	}
	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}

	if len(params) > 1 && params[1] != "" {
		if typesIncl, err = typesToMask(strings.Split(params[1], ",")); err != nil {
			return nil, err
		}
	}

	if len(params) > 2 && params[2] != "" {
		if typesExcl, err = typesToMask(strings.Split(params[2], ",")); err != nil {
			return nil, err
		}
	}

	if typesIncl == 0 {
		if typesExcl == 0 {
			typesIncl.AddType(zbxFtFile)
		} else {
			typesIncl.AddType(zbxFtAllMask)
		}
	}

	typesIncl.ClearType(typesExcl)

	if f, err := os.Lstat(params[0]); err == nil {
		if (f.Mode().IsRegular() && typesIncl.HasType(zbxFtFile)) ||
			(f.Mode().IsDir() && typesIncl.HasType(zbxFtDir)) ||
			(f.Mode()&os.ModeSymlink != 0 && typesIncl.HasType(zbxFtSym)) ||
			(f.Mode()&os.ModeSocket != 0 && typesIncl.HasType(zbxFtSock)) ||
			(f.Mode()&os.ModeDevice != 0 && typesIncl.HasType(zbxFtBdev)) ||
			(f.Mode()&os.ModeCharDevice != 0 && typesIncl.HasType(zbxFtCdev)) ||
			(f.Mode()&os.ModeNamedPipe != 0 && typesIncl.HasType(zbxFtFifo)) {
			return 1, nil
		}
	}
	return 0, nil
}
