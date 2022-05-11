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

package file

import (
	"bytes"
	"errors"
	"fmt"
)

func (p *Plugin) exportContents(params []string) (result interface{}, err error) {

	if len(params) != 1 && len(params) != 2 {
		return nil, errors.New("Wrong number of parameters")
	}

	var encoder string

	if len(params) == 2 {
		encoder = params[1]
	}

	f, err := stdOs.Stat(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain file %s information: %s", params[0], err)
	}
	filelen := f.Size()

	bnum := 64 * 1024
	if filelen > int64(bnum) {
		return nil, errors.New("File is too large for this check")
	}

	file, err := stdOs.Open(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot open file %s: %s", params[0], err)
	}
	defer file.Close()

	buf := bytes.Buffer{}
	if _, err = buf.ReadFrom(file); err != nil {
		return nil, fmt.Errorf("Cannot read from file: %s", err)
	}

	outbuf := decode(encoder, buf.Bytes())

	return string(bytes.TrimRight(outbuf, "\n\r")), nil

}
