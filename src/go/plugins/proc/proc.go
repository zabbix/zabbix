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

package proc

func addNonNegative(dst *int64, val int64) () {
	if *dst == -1 {
		return
	}

	if val == -1 {
		*dst = -1
		return
	}

	*dst += val
	return
}

func addNonNegativeFloat(dst *float64, val float64) () {
	if *dst == -1.0 {
		return
	}

	if val == -1.0 {
		*dst = -1.0
		return
	}

	*dst += val
	return
}

