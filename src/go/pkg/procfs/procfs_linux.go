/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package procfs

import (
	"fmt"

	"golang.zabbix.com/sdk/errs"
)

// GetMemory reads /proc/meminfo file and returns and returns the value in bytes for the
// specific memory type. Returns an error if the value was not found, or if there is an issue
// with reading the file or parsing the value.
func GetMemory(memType string) (uint64, error) {
	meminfo, err := ReadAll("/proc/meminfo")
	if err != nil {
		return 0, errs.New("cannot read meminfo file: " + err.Error())
	}

	mem, found, err := ByteFromProcFileData(meminfo, memType)
	if err != nil {
		return 0, errs.New(fmt.Sprintf("cannot get the memory amount for %s: %s", memType, err.Error()))
	}

	if !found {
		return mem, errs.New("cannot get the memory amount for " + memType)
	}

	return mem, nil
}
