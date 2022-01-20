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
				for _, f := range cp.files {
					if os.SameFile(f, i) {
						return true
					}
				}

				cp.common.files = append(cp.common.files, i)
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
