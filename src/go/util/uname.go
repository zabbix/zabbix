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

package util

func UnameArrayToString[T ~int8 | ~uint8](unameArray *[65]T) string {
	var byteString [65]byte
	var indexLength int
	for ; indexLength < len(unameArray); indexLength++ {
		if 0 == unameArray[indexLength] {
			break
		}
		byteString[indexLength] = byte(unameArray[indexLength])
	}

	return string(byteString[:indexLength])
}
