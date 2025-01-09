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

package dir

import (
	"io/fs"
	"syscall"
)

func (cp *countParams) skipType(path string, d fs.DirEntry) bool {
	var typeFile bool
	var typeDir bool

	i, err := d.Info()
	if err != nil {
		impl.Logger.Errf("failed to get file info for path %s, %s", path, err.Error())
		return true
	}

	if attr, ok := i.Sys().(*syscall.Win32FileAttributeData); ok {
		if (len(cp.typesInclude) > 0 && !isTypeMatch(cp.typesInclude, regularFile)) ||
			(len(cp.typesExclude) > 0 && isTypeMatch(cp.typesExclude, regularFile)) {
			typeFile = false
		} else {
			typeFile = true
		}

		if (len(cp.typesInclude) > 0 && !isTypeMatch(cp.typesInclude, fs.ModeDir)) ||
			(len(cp.typesExclude) > 0 && isTypeMatch(cp.typesExclude, fs.ModeDir)) {
			typeDir = false
		} else {
			typeDir = true
		}

		if attr.FileAttributes&syscall.FILE_ATTRIBUTE_REPARSE_POINT == 0 {
			if attr.FileAttributes&syscall.FILE_ATTRIBUTE_DIRECTORY != 0 && typeDir {
				return false
			} else if attr.FileAttributes&syscall.FILE_ATTRIBUTE_DIRECTORY == 0 && typeFile {
				return false
			}
		} else if attr.FileAttributes&syscall.FILE_ATTRIBUTE_DIRECTORY == 0 && typeFile {
			return false
		}
	} else {
		impl.Logger.Errf("failed to get system file attribute data")
	}

	return true
}
