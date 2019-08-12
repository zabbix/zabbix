/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package fileregexp

import (
	"bufio"
	"bytes"
	"errors"
	"fmt"
	"io"
	"strconv"
	"time"
	"zabbix/internal/plugin"
	"zabbix/pkg/std"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

func decode(encoder string, inbuf []byte) (outbuf []byte) {

	return inbuf
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var startline, endline, curline uint64
	var encoder string

	if len(params) > 6 {
		return nil, errors.New("Too many parameters")
	}
	if len(params) < 1 || "" == params[0] {
		return nil, errors.New("Invalid first parameter")
	}
	if len(params) < 2 || "" == params[1] {
		return nil, errors.New("Invalid second parameter")
	}
	if len(params) > 2 {
		encoder = params[2]
	}

	if len(params) < 4 || "" == params[3] {
		startline = 0
	} else {
		startline, err = strconv.ParseUint(params[3], 10, 32)
		if err != nil {
			return nil, errors.New("Invalid fourth parameter")
		}
	}
	if len(params) < 5 || "" == params[4] {
		endline = 0xffffffff
	} else {
		endline, err = strconv.ParseUint(params[4], 10, 32)
		if err != nil {
			return nil, errors.New("Invalid fifth parameter")
		}
	}
	if startline > endline {
		return nil, errors.New("Start line parameter must not exceed end line")
	}
	start := time.Now()
	file, err := stdOs.Open(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot open file %s: %s", params[0], err)
	}
	defer file.Close()

	// Start reading from the file with a reader.
	reader := bufio.NewReader(file)

	var line string
	curline = 0

	for {
		elapsed := time.Since(start)
		if elapsed.Seconds() > /*float64(agent.Options.Timeout)*/ 3000 {
			return nil, errors.New("Timeout while processing item")
		}

		line, err = reader.ReadString('\n')

		if err != nil {
			if err != io.EOF {
				return nil, errors.New("Cannot read from file")
			}
			break
		}

		curline++
		if curline >= startline {
			outline := string(bytes.TrimRight(decode(encoder, []byte(line)), "\n\r"))
			fmt.Printf("AKDBG %s\n", outline)
		}

		if curline >= endline {
			break
		}

	}

	return 1, nil

}

var stdOs std.Os

func init() {
	plugin.RegisterMetric(&impl, "regexp", "vfs.file.regexp", "Find string in a file")
	stdOs = std.NewOs()
}
