//go:build !windows
// +build !windows

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

import "io/fs"

func (cp *countParams) skipType(path string, d fs.DirEntry) bool {
	if len(cp.typesInclude) > 0 && !isTypeMatch(cp.typesInclude, d.Type()) {
		return true
	}

	if len(cp.typesExclude) > 0 && isTypeMatch(cp.typesExclude, d.Type()) {
		return true
	}

	return false
}
