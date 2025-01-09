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

package file

import (
	"errors"
	"fmt"
	"math"
	"os"
	"regexp"
	"strconv"
	"strings"
	"time"

	"golang.zabbix.com/agent2/pkg/zbxregexp"
)

func (p *Plugin) exportRegexp(params []string, timeout int) (result interface{}, err error) {
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

	var encoding string
	if len(params) > 2 {
		encoding = params[2]
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

	var compiledRegexp *regexp.Regexp
	if compiledRegexp, err = regexp.Compile(params[1]); err != nil {
		return nil, errors.New("Invalid first parameter.")
	}

	f, err := os.Open(params[0])

	if err != nil {
		return nil, err
	}
	defer f.Close()

	initial := true
	undecodedBufNumBytes := 0
	var undecodedBuf []byte
	for 0 < undecodedBufNumBytes || initial {
		elapsed := time.Since(start)

		if elapsed.Seconds() > float64(timeout) {
			return nil, errors.New("Timeout while processing item.")
		}

		initial = false
		curline++
		undecodedBuf, undecodedBufNumBytes, encoding, err = p.readTextLineFromFile(f, encoding)
		if err != nil {
			return nil, err
		}
		utf8_buf, utf8_bufNumBytes, err := decodeToUTF8(encoding, undecodedBuf, undecodedBufNumBytes)
		if err != nil {
			return nil, fmt.Errorf("Failed to convert from encoding to utf8: %w", err)
		}

		utf8_bufStr := string(utf8_buf[:utf8_bufNumBytes])
		utf8_bufStr = strings.TrimRight(utf8_bufStr, "\r\n")

		if curline >= startline {
			if out, ok := zbxregexp.ExecuteRegex([]byte(utf8_bufStr), compiledRegexp, []byte(output)); ok {
				return out, nil
			}
		}

		if curline >= endline {
			break
		}
	}

	return "", nil
}
