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

package wildcard

import "strings"

// Minimize removes repeated wildcard characters from the expression
func Minimize(pattern string) string {
	var last rune
	var res strings.Builder

	for _, c := range pattern {
		if c == '*' && last == '*' {
			continue
		}
		last = c
		_, _ = res.WriteRune(c)
	}
	return res.String()
}

// Match matches string value to specified wildcard.
// Asterisk (*) characters match to any characters of any length.
// It mirrors zbx_wildcard_match in src/libs/zbxregexp/zbxregexp.c.
//
//nolint:cyclop, gocyclo
func Match(value, pattern string) bool {
	var vi, pi, vp, pp = 0, 0, 0, 0

	for vi < len(value) && (pi >= len(pattern) || pattern[pi] != '*') {
		if pi >= len(pattern) || value[vi] != pattern[pi] {
			return false
		}

		vi++
		pi++
	}

	for vi < len(value) {
		switch {
		case pi < len(pattern) && pattern[pi] == '*':
			pi++
			if pi >= len(pattern) {
				return true
			}

			pp, vp = pi, vi+1
		case pi < len(pattern) && value[vi] == pattern[pi]:
			pi++
			vi++
		default:
			pi = pp
			vi = vp
			vp++
		}
	}

	for pi < len(pattern) && pattern[pi] == '*' {
		pi++
	}

	return pi == len(pattern)
}
