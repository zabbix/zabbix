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
func Match(value string, pattern string) bool {
	var vi, pi, vp, pp int = 0, 0, 0, 0

	for i, c := range pattern {
		if c == '*' {
			vi, pi = i, i
			break
		}
		if i >= len(value) {
			break
		}
		if pattern[i] != value[i] {
			return false
		}
	}
	for vi < len(value) && pi < len(pattern) {
		if pattern[pi] == '*' {
			pi++
			if pi >= len(pattern) {
				return true
			}
			pp, vp = pi, vi+1
		} else if value[vi] == pattern[pi] {
			pi++
			vi++
		} else {
			pi = pp
			vi = vp
			vp++
		}
		if pi >= len(pattern) && vi < len(value) {
			pi = pp
			vi = vp
			vp++
		}
	}
	if vi < len(value) {
		return len(pattern) > 0 && pattern[len(pattern)-1] == '*'
	}
	for ; pi < len(pattern); pi++ {
		if pattern[pi] != '*' {
			break
		}
	}

	return pi >= len(pattern)
}
