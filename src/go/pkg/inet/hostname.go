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

// IsRFCExtendedHostName checks if host is valid host name (should work the same as
// zbx_is_rfc_extended_hostname() in C)
//
// Valid host names for this function are names with only ASCII characters 0-9, A-Z, a-z,
// hyphen ('-') and dot ('.').
// Additionally underscore ('_') is allowed as Windows host names allow it.
// Internationalized Domain Names with multibyte UTF-8 characters will be rejected as not
// valid (Punycode can be used).
//
//nolint:cyclop,gocyclo // high complexity due to host name validation, splitting not practical
func IsRFCExtendedHostName(host string) bool {
	// Requirements and limits for host names are defined in RFC 1035,
	// with clarifications in RFC 1123, RFC 2181.
	//
	// Total length should not exceed 253 characters.
	// This is excluding trailing dot and additional byte usually used when saved.
	n := len(host)
	if n == 0 || n > 253 {
		return false
	}

	isPurelyNumeric := true // detect numeric-only names

	// first character must be alphanumeric, additionally underscore ('_') is allowed.
	c := host[0]
	if !isAlnumASCII(c) && c != '_' {
		return false
	}

	if c > '9' || c < '0' {
		isPurelyNumeric = false
	}

	labelLen := 1
	prevHyphen := false

	for i := 1; i < n; i++ {
		c = host[i]

		switch {
		case isAlnumASCII(c):
			labelLen++
			prevHyphen = false

			if c > '9' || c < '0' {
				isPurelyNumeric = false
			}
		case c == '-':
			// label must not start with hyphen
			if labelLen == 0 {
				return false
			}

			labelLen++
			prevHyphen = true
			isPurelyNumeric = false
		case c == '.':
			// empty label or label ending with hyphen
			if labelLen == 0 || prevHyphen {
				return false
			}

			labelLen = 0
			prevHyphen = false
		case c == '_':
			labelLen++
			prevHyphen = false
		default:
			return false
		}

		if labelLen > 63 {
			return false
		}
	}

	// last label must not be empty or end with hyphen
	if labelLen == 0 || prevHyphen {
		return false
	}

	// reject purely numeric names
	if isPurelyNumeric {
		return false
	}

	return true
}

func isAlnumASCII(c byte) bool {
	return (c >= '0' && c <= '9') ||
		(c >= 'a' && c <= 'z') ||
		(c >= 'A' && c <= 'Z')
}
