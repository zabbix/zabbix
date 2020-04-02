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

package memcached

import "strings"

type zabbixError struct {
	err string
}

func (e zabbixError) Error() string {
	errText := e.err
	if errText[len(errText)-1:] != "." {
		errText += "."
	}

	return strings.Title(errText)
}

var (
	errorInvalidParams     = zabbixError{"invalid parameters"}
	errorTooManyParameters = zabbixError{"too many parameters"}
	errorCannotFetchData   = zabbixError{"cannot fetch data"}
	errorCannotMarshalJSON = zabbixError{"cannot marshal JSON"}
	errorUnsupportedMetric = zabbixError{"unsupported metric"}
	errorEmptyResult       = zabbixError{"empty result"}
	errorUnknownSession    = zabbixError{"unknown session"}
)
