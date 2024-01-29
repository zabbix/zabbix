/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

package file

import (
	"bytes"
	"errors"
	"fmt"
	"strings"
)

func (p *Plugin) exportContents(params []string) (result interface{}, err error) {
	const maxFileLen = 16 * 1024 * 1024

	if len(params) != 1 && len(params) != 2 {
		return nil, errors.New("Wrong number of parameters")
	}

	var encoding string

	if len(params) == 2 {
		encoding = params[1]
	}

	f, err := stdOs.Stat(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain file %s information: %s", params[0], err)
	}
	filelen := f.Size()

	if filelen > int64(maxFileLen) {
		return nil, errors.New("File is too large for this check")
	}

	file, err := stdOs.Open(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot open file %s: %s", params[0], err)
	}
	defer file.Close()

	undecodedBuf := bytes.Buffer{}
	if _, err = undecodedBuf.ReadFrom(file); err != nil {
		return nil, fmt.Errorf("Cannot read from file: %s", err)
	}
	encoding = findEncodingFromBOM(encoding, undecodedBuf.Bytes(), len(undecodedBuf.Bytes()))
	utf8_buf, utf8_bufNumBytes := decodeToUTF8(encoding, undecodedBuf.Bytes(), len(undecodedBuf.Bytes()))
	utf8_bufStr := string(utf8_buf[:utf8_bufNumBytes])

	return strings.TrimRight(utf8_bufStr, "\n\r"), nil
}
