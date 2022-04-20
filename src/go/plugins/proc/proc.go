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

