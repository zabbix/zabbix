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

package zbxregexp

import (
	"bytes"
	"regexp"
)

func ExecuteRegex(line []byte, rx *regexp.Regexp, output []byte) (result string, match bool) {
	matches := rx.FindSubmatchIndex(line)
	if len(matches) == 0 {
		return "", false
	}
	if len(output) == 0 {
		return string(line), true
	}

	buf := &bytes.Buffer{}
	for len(output) > 0 {
		pos := bytes.Index(output, []byte{'\\'})
		if pos == -1 || pos == len(output)-1 {
			break
		}
		_, _ = buf.Write(output[:pos])
		switch output[pos+1] {
		case '0', '1', '2', '3', '4', '5', '6', '7', '8', '9':
			i := output[pos+1] - '0'
			if len(matches) >= int(i)*2+2 {
				if matches[i*2] != -1 {
					_, _ = buf.Write(line[matches[i*2]:matches[i*2+1]])
				}
			}
			pos++
		case '@':
			_, _ = buf.Write(line[matches[0]:matches[1]])
			pos++
		case '\\':
			_ = buf.WriteByte('\\')
			pos++
		default:
			_ = buf.WriteByte('\\')
		}
		output = output[pos+1:]
	}
	_, _ = buf.Write(output)
	return buf.String(), true
}
