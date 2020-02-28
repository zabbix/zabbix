/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	"bufio"
	"bytes"
	"errors"
	"fmt"
	"math"
	"regexp"
	"strconv"
	"time"
)

func (p *Plugin) executeRegex(line []byte, rx *regexp.Regexp, output []byte) (result string, match bool) {
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

func (p *Plugin) exportRegexp(params []string) (result interface{}, err error) {
	var startline, endline, curline uint64

	start := time.Now()

	if len(params) > 6 {
		return nil, errors.New("Too many parameters.")
	}
	if len(params) < 1 || "" == params[0] {
		return nil, errors.New("Invalid first parameter.")
	}
	if len(params) < 2 || "" == params[1] {
		return nil, errors.New("Invalid second parameter.")
	}

	var encoder string
	if len(params) > 2 {
		encoder = params[2]
	}

	if len(params) < 4 || "" == params[3] {
		startline = 0
	} else {
		startline, err = strconv.ParseUint(params[3], 10, 32)
		if err != nil {
			return nil, errors.New("Invalid fourth parameter.")
		}
	}
	if len(params) < 5 || "" == params[4] {
		endline = math.MaxUint64
	} else {
		endline, err = strconv.ParseUint(params[4], 10, 32)
		if err != nil {
			return nil, errors.New("Invalid fifth parameter.")
		}
	}
	if startline > endline {
		return nil, errors.New("Start line parameter must not exceed end line.")
	}
	var output string
	if len(params) == 6 {
		output = params[5]
	}

	var rx *regexp.Regexp
	if rx, err = regexp.Compile(params[1]); err != nil {
		return nil, errors.New("Invalid first parameter.")
	}

	file, err := stdOs.Open(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot open file %s: %s", params[0], err)
	}
	defer file.Close()

	// Start reading from the file with a reader.
	scanner := bufio.NewScanner(file)
	curline = 0
	for scanner.Scan() {
		elapsed := time.Since(start)
		if elapsed.Seconds() > float64(p.options.Timeout) {
			return nil, errors.New("Timeout while processing item.")
		}

		curline++
		if curline >= startline {
			if out, ok := p.executeRegex(decode(encoder, scanner.Bytes()), rx, []byte(output)); ok {
				return out, nil
			}
		}
		if curline >= endline {
			break
		}
	}
	return "", nil
}
