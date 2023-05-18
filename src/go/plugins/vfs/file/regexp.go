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
	//	"bufio"
	"bytes"
	"os"
	//"git.zabbix.com/ap/plugin-support/log"
	"errors"
	"fmt"
	"math"
	"regexp"
	"strconv"
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
	fmt.Printf("STRATA 2 type: %T\n", f)

	if e != nil {
		fmt.Printf("FAILED!!! XX: +i%v\n", e)
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
			fmt.Printf("FORD PRE-FINAL RES: ->%v+<-\n", err)
			return nil, err
		}

		for f := 0; f < nbytes; f++ {
			fmt.Printf("FORD ress buf: ->%x<-\n", buf[f])
		}

		fmt.Printf("LAMBDA decode: %d\n", nbytes)
		x := decode(encoder, buf, nbytes)
		if curline >= startline {
			for _, m := range bytes.Split(x, []byte("\n")) {
				fmt.Printf("FORD LINE X: %s\n", m)
			}
			fmt.Printf("NEXT")
			if out, ok := zbxregexp.ExecuteRegex(x, rx, []byte(output)); ok {
				return out, nil
			}
		}

	 	if curline >= endline {
	 		break
	 	}

	}
	return "", nil
}
