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

package zbxerr

import (
	"errors"
	"unicode"
)

type ZabbixError struct {
	err   error
	cause error
}

// New creates a new ZabbixError
func New(msg string) ZabbixError {
	return ZabbixError{errors.New(msg), nil}
}

// Wrap creates a new ZabbixError with wrapped cause
func (e ZabbixError) Wrap(cause error) error {
	return ZabbixError{err: e, cause: cause}
}

// Unwrap extracts an original underlying error
func (e ZabbixError) Unwrap() error {
	return e.err
}

// Cause returns a cause of original error
func (e ZabbixError) Cause() error {
	return e.cause
}

// Error stringifies an error according to Zabbix requirements:
// * the first letter must be capitalized;
// * an error text should be trailed by a dot.
func (e ZabbixError) Error() string {
	var msg string

	ucFirst := func(str string) string {
		for i, v := range str {
			return string(unicode.ToUpper(v)) + str[i+1:]
		}

		return ""
	}

	if zbxErr, ok := e.err.(ZabbixError); ok {
		msg = zbxErr.Raw()
	} else {
		msg = e.err.Error()
	}

	if e.cause != nil {
		msg += ": " + e.cause.Error()
	}

	if msg[len(msg)-1:] != "." {
		msg += "."
	}

	return ucFirst(msg)
}

// Raw returns a non-modified error message
func (e ZabbixError) Raw() string {
	return e.err.Error()
}

var (
	ErrorInvalidParams        = New("invalid parameters")
	ErrorTooFewParameters     = New("too few parameters")
	ErrorTooManyParameters    = New("too many parameters")
	ErrorInvalidConfiguration = New("invalid configuration")
	ErrorCannotFetchData      = New("cannot fetch data")
	ErrorCannotUnmarshalJSON  = New("cannot unmarshal JSON")
	ErrorCannotMarshalJSON    = New("cannot marshal JSON")
	ErrorCannotParseResult    = New("cannot parse result")
	ErrorConnectionFailed     = New("connection failed")
	ErrorUnsupportedMetric    = New("unsupported metric")
	ErrorEmptyResult          = New("empty result")
	ErrorUnknownSession       = New("unknown session")
)
