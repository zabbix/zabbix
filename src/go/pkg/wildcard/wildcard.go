/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

// Match reports whether the entire value matches pattern.
// Asterisk (*) matches any number of bytes, including zero.
// Matching follows zbx_wildcard_match in src/libs/zbxregexp/zbxregexp.c.
func Match(value, pattern string) bool {
	patternParts := strings.Split(pattern, "*")
	if len(patternParts) == 1 {
		return value == pattern
	}

	// The first and last literal parts must match the ends of the value.
	var (
		prefix = patternParts[0]
		suffix = patternParts[len(patternParts)-1]
	)

	if !strings.HasPrefix(value, prefix) {
		return false
	}

	value = value[len(prefix):]
	if !strings.HasSuffix(value, suffix) {
		return false
	}

	value = value[:len(value)-len(suffix)]

	// Asterisks cover the gaps between the remaining literal parts.
	middleParts := patternParts[1 : len(patternParts)-1]
	for _, middlePart := range middleParts {
		index := strings.Index(value, middlePart)
		if index == -1 {
			return false
		}

		value = value[index+len(middlePart):]
	}

	return true
}
