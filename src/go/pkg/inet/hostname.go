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

package inet

func isAlnum(c byte) bool {
	return (c >= '0' && c <= '9') ||
		(c >= 'a' && c <= 'z') ||
		(c >= 'A' && c <= 'Z')
}

// IsDNSName checks if host is valid DNS name (should work the same as zbx_is_dnsname in C)
//
//nolint:cyclop,gocyclo // high complexity due to DNS validation, splitting not practical
func IsDNSName(host string) bool {
	n := len(host)
	if n == 0 || n > 253 {
		return false
	}

	// first character must be alphanumeric
	c := host[0]
	if !isAlnum(c) {
		return false
	}

	labelLen := 1
	prevDash := false

	for i := 1; i < n; i++ {
		c = host[i]

		switch {
		case isAlnum(c):
			labelLen++
			prevDash = false
		case c == '-':
			// label must not start with dash
			if labelLen == 0 {
				return false
			}

			labelLen++
			prevDash = true
		case c == '.':
			// empty label or label ending with dash
			if labelLen == 0 || prevDash {
				return false
			}

			labelLen = 0
			prevDash = false
		default:
			return false
		}

		if labelLen > 63 {
			return false
		}
	}

	// last label must not be empty or end with dash
	if labelLen == 0 || prevDash {
		return false
	}

	return true
}
