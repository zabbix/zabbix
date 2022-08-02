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
	zbxFtAll = (zbxFtFile | zbxFtDir | zbxFtSym | zbxFtSock | zbxFtBdev | zbxFtCdev | zbxFtFifo)
	zbxFtDev = (zbxFtBdev | zbxFtCdev)
)

type fileType uint16

func (f fileType) hasType(t fileType) bool { return f&t != 0 }
func (f *fileType) addType(t fileType)     { *f |= t }

func typesToMask(param string) (fileType, error) {
	var mask fileType = 0
	template := map[string]fileType{
		"file": zbxFtFile,
		"dir":  zbxFtDir,
		"sym":  zbxFtSym,
		"sock": zbxFtSock,
		"bdev": zbxFtBdev,
		"cdev": zbxFtCdev,
		"fifo": zbxFtFifo,
		"all":  zbxFtAll,
		"dev":  zbxFtDev,
	}

	if strings.TrimSpace(param) == "" {
		return 0, nil
	}

	types := strings.Split(param, ",")

	for _, name := range types {
		name = strings.TrimSpace(name)
		if t, ok := template[name]; ok {
			mask.addType(t)
		} else {
			return 0, fmt.Errorf(`Invalid type "%s".`, name)
		}
	}

	return mask, nil
}

// Export -
func (p *Plugin) exportExists(params []string) (result interface{}, err error) {
	var typesIncl fileType
	var typesExcl fileType
	var types fileType
	var f os.FileInfo

	if len(params) > 3 {
		return nil, errors.New("Too many parameters.")
	}
	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}

	if len(params) > 1 && params[1] != "" {
		if typesIncl, err = typesToMask(params[1]); err != nil {
			return nil, err
		}
	}

	if len(params) > 2 && params[2] != "" {
		if typesExcl, err = typesToMask(params[2]); err != nil {
			return nil, err
		}
	}

	if typesIncl == 0 {
		if typesExcl == 0 {
			typesIncl.addType(zbxFtFile)
		} else {
			typesIncl.addType(zbxFtAll)
		}
	}

	if typesIncl.hasType(zbxFtSym) || typesExcl.hasType(zbxFtSym) {
		if f, err = os.Lstat(params[0]); err == nil {
			if f.Mode()&os.ModeSymlink != 0 {
				types.addType(zbxFtSym)
			}
		} else if !os.IsNotExist(err) {
			return 0, fmt.Errorf("Cannot obtain file information: %s", err)
		}
	}

	if f, err = stdOs.Stat(params[0]); err == nil {
		if f.Mode().IsRegular() {
			types.addType(zbxFtFile)
		} else if f.Mode().IsDir() {
			types.addType(zbxFtDir)
		} else if f.Mode()&os.ModeSocket != 0 {
			types.addType(zbxFtSock)
		} else if f.Mode()&os.ModeCharDevice != 0 {
			types.addType(zbxFtCdev)
		} else if f.Mode()&os.ModeDevice != 0 {
			types.addType(zbxFtBdev)
		} else if f.Mode()&os.ModeNamedPipe != 0 {
			types.addType(zbxFtFifo)
		}
	} else if !os.IsNotExist(err) {
		return 0, fmt.Errorf("Cannot obtain file information: %s", err)
	}

	if !typesExcl.hasType(types) && typesIncl.hasType(types) {
		return 1, nil
	}
	return 0, nil
}
