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

package filecontents

import (
	"bufio"
	"bytes"
	"errors"
	"fmt"
	"io"
	"regexp"
	"strconv"
	"strings"
	"time"
	"zabbix/internal/agent"
	"zabbix/internal/plugin"
)

const maxNumberOfGroups int = 10

func executeRegex(line string, pattern string, output string) (result string, err error) {
	retline := ""

	regx, err := regexp.Compile(pattern)
	if err != nil {
		return retline, err
	}

	Idxs := regx.FindAllSubmatchIndex([]byte(line), maxNumberOfGroups)

	if len(Idxs) != 0 {
		if output != "" {
			var i int
			var toreplace, replaceby string

			retline = output

			if len(Idxs) > 1 {
				replaceby = fmt.Sprintf("%s", line[Idxs[1][0]:Idxs[1][1]])
				toreplace = "\\@"
				retline = strings.Replace(retline, toreplace, replaceby, -1)
			}

			for i < maxNumberOfGroups {
				toreplace = fmt.Sprintf("\\%d", i)
				replaceby = ""
				if i < len(Idxs) {
					replaceby = fmt.Sprintf("%s", line[Idxs[i][0]:Idxs[i][1]])
				}
				retline = strings.Replace(retline, toreplace, replaceby, -1)
				i++
			}

		} else {
			retline = line
		}
	}

	return retline, nil
}

func exportRegexp(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	var startline, endline, curline uint64
	var encoder, output string
	var line, outline string

	start := time.Now()

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
	if len(params) == 6 {
		output = params[5]
	}

	file, err := stdOs.Open(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot open file %s: %s", params[0], err)
	}
	defer file.Close()

	// Start reading from the file with a reader.
	reader := bufio.NewReader(file)

	curline = 0

	for {
		elapsed := time.Since(start)
		if elapsed.Seconds() > float64(agent.Options.Timeout) {
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
			line := string(bytes.TrimRight(decode(encoder, []byte(line)), "\n\r"))
			outline, err = executeRegex(line, params[1], output)
			if err != nil {
				return nil, fmt.Errorf("Cannot execute regex %s: %s", params[1], err)
			}
			if outline != "" {
				break
			}
		}

		if curline >= endline {
			break
		}

	}

	return outline, nil

}
