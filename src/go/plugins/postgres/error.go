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

package postgres

import (
	"fmt"
	"strings"
)

type zabbixError string

func (e zabbixError) Error() string { return string(e) }

const (
	errorTooManyParameters               = zabbixError("Too many parameters.")
	errorCannotFetchData                 = zabbixError("Cannot fetch data.")
	errorPostgresPing                    = zabbixError("PostgreSQL ping failed: got wrong data.")
	errorCannotParseData                 = zabbixError("Cannot parse data.")
	errorUnsupportedMetric               = zabbixError("Unsupported metric.")
	errorEmptyResult                     = zabbixError("Empty result.")
	errorCannotConvertPostgresVersionInt = zabbixError("Cannot convert Postgres version to integer.")
	errorFourthParamEmpty                = zabbixError("The key requires database name as fourth parameter")
	errorFourthParamLen                  = zabbixError("Expected database name as fourth parameter for the key, got empty string")
	errorUnknownSession                  = zabbixError("Unknown session")
)

// formatZabbixError formats a given error text. It capitalizes the first letter and adds a dot to the end.
// TBD: move to the agent's core
func formatZabbixError(errText string) string {
	if errText[len(errText)-1:] != "." {
		errText = errText + "."
	}

	return strings.Title(errText)
}

func sanitizeError(errText, connString string) (err error) {
	// default error string shows user credentials used to establish connection
	// this can be potentialy dangerous
	// create error message string without sensitive info
	var (
		originSchema, sanitizedErrorString string
		startingIndexOfError               int
	)
	if strings.Contains(connString, "host=") {
		originSchema = "unix://"
	} else {
		originSchema = "tcp://"
	}
	// want to get only uri from conn string for the error message
	splitted := strings.Split(connString, "@")
	if len(splitted) > 1 {
		// create error message without username and password and with the right schemaname
		startingIndexOfError = strings.Index(errText, connString) + len(connString) + 2
		sanitizedErrorString = fmt.Sprintf("%s%s  %s ", originSchema, splitted[1], errText[startingIndexOfError:])
	} else {
		// should never happen
		sanitizedErrorString = errText[len(connString):]
	}
	return fmt.Errorf("invalid connection string: %s:", sanitizedErrorString)
}
