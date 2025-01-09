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
	"time"
)

const MAX_BUFFER_LEN = 65536

func (p *Plugin) exportRegmatch(params []string, timeout int) (result interface{}, err error) {
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

	ret := 0
	r, err := regexp.Compile(params[1])
	if err != nil {
		return nil, fmt.Errorf("Cannot compile regular expression %s: %s", params[1], err)
	}

	elapsed := time.Since(start)

	if elapsed.Seconds() > float64(timeout) {
		return nil, errors.New("Timeout while processing item.")
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
		initial = false
		elapsed := time.Since(start)
		if elapsed.Seconds() > float64(timeout) {
			return nil, errors.New("Timeout while processing item.")
		}

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

		if curline >= startline {
			if match := r.Match([]byte(utf8_bufStr)); match {
				ret = 1
			}
		}

		if curline >= endline {
			break
		}
	}

	return ret, nil
}
