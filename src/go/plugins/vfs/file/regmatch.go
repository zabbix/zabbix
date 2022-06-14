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
	"bufio"
	"errors"
	"fmt"
	"math"
	"regexp"
	"strconv"
	"time"
)

func (p *Plugin) exportRegmatch(params []string) (result interface{}, err error) {
	var startline, endline, curline uint64

	start := time.Now()

	if len(params) > 5 {
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

	file, err := stdOs.Open(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot open file %s: %s", params[0], err)
	}
	defer file.Close()

	// Start reading from the file with a reader.
	scanner := bufio.NewScanner(file)
	curline = 0
	ret := 0
	r, err := regexp.Compile(params[1])
	if err != nil {
		return nil, fmt.Errorf("Cannot compile regular expression %s: %s", params[1], err)
	}

	for scanner.Scan() {
		elapsed := time.Since(start)
		if elapsed.Seconds() > float64(p.options.Timeout) {
			return nil, errors.New("Timeout while processing item.")
		}

		curline++
		if curline >= startline {
			if match := r.Match(decode(encoder, scanner.Bytes())); match {
				ret = 1
			}
		}
		if curline >= endline {
			break
		}
	}
	return ret, nil
}
