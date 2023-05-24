/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	//	"bytes"
	"errors"
	"fmt"
	"math"
	"os"
	"regexp"
	"strconv"
	"strings"
	"time"
	"zabbix.com/pkg/zbxregexp"
)

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

	f, e := os.Open(params[0])

	if e != nil {
		return nil, e
	}
	defer f.Close()

	initial := true
	nbytes := 0
	var buf []byte
	for 0 < nbytes || initial {
		elapsed := time.Since(start)

		if elapsed.Seconds() > float64(p.options.Timeout) {
			return nil, errors.New("Timeout while processing item.")
		}

		initial = false
		curline++
		buf, nbytes, err = p.readFile(f, encoder)
		if err != nil {
			return nil, err
		}
		for ii := 0; ii < nbytes; ii++ {
			fmt.Printf("BUF NEXT2: %x, %c\n", buf[ii], buf[ii])
		}
		x, outbytes := decode(encoder, buf, nbytes)
		fmt.Printf("OUTBYTES: %d", outbytes)
		fmt.Printf("GRANDO 22: ->%s<-\n", x)

		xs := string(x[:outbytes])
		fmt.Printf("GRANDO P: ->%s<-\n", xs)

		//xs = string(bytes.TrimRight(xs, "\n\r"))
		for ii := 0; ii < len(xs); ii++ {
			fmt.Printf("AGS: %x\n", xs[ii])
		}

		xs = strings.TrimRight(xs, "\r\n")
		for ii := 0; ii < len(xs); ii++ {
			fmt.Printf("AGS2: %x\n", xs[ii])
		}

		fmt.Printf("GRANDO 1: ->%s<-\n", xs)
		// for ii:=0; ii< len(); ii++ {
		// 	fmt.Printf("XXX: %x, %c\n", xs[ii], x[ii])
		// }
		fmt.Printf("EEEEEEEEEE")
		if curline >= startline {
			if out, ok := zbxregexp.ExecuteRegex([]byte(xs), rx, []byte(output)); ok {
				return out, nil
			}
		}

		if curline >= endline {
			break
		}
	}
	return "", nil
}
