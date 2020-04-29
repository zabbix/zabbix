/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package mysql

import "strings"

type zabbixError string

func (e zabbixError) Error() string { return string(e) }

const (
	errorTooManyParameters  = zabbixError("Too many parameters")
	errorTooFewParameters   = zabbixError("Too few parameters")
	errorDBnameMissing      = zabbixError("There is no database name as the fourth parameter")
	errorParameterNotURI    = zabbixError("The first parameter is not URI or session")
	errorConnectionNotFound = zabbixError("Active connection is not found")
	errorConnectionKilled   = zabbixError("Connection was killed")
	errorUserPassword       = zabbixError("The username and password cannot be used with the session name")
	errorNoReplication      = zabbixError("Replication is not configured")
)

// formatZabbixError formats a given error text. It capitalizes the first letter and adds a dot to the end.
// TBD: move to the agent's core
func formatZabbixError(errText string) string {
	if errText[len(errText)-1:] != "." {
		errText += "."
	}

	return strings.Title(errText)
}
