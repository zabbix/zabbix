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
	"bytes"
	"errors"
	"fmt"
	"io"
	"os"

	"golang.zabbix.com/sdk/zbxerr"
)

// Export -
func (p *Plugin) exportSize(params []string) (result interface{}, err error) {
	if len(params) == 0 || len(params) > 2 {
		return nil, errors.New("Invalid number of parameters.")
	}
	if "" == params[0] {
		return nil, errors.New("Invalid first parameter.")
	}
	mode := "bytes"
	if len(params) == 2 && len(params[1]) != 0 {
		mode = params[1]
	}

	switch mode {
	case "bytes":
		if f, err := stdOs.Stat(params[0]); err == nil {
			return f.Size(), nil
		} else {
			return nil, zbxerr.New("Cannot obtain file information").Wrap(err)
		}
	case "lines":
		return newlineCounter(params[0])
	default:
		return nil, errors.New("Invalid second parameter.")
	}
}

// lineCounter - count number of newline in file
func newlineCounter(fileName string) (result interface{}, err error) {
	var file *os.File
	if file, err = os.Open(fileName); err != nil {
		return nil, zbxerr.New("Invalid first parameter").Wrap(err)
	}
	defer file.Close()
	buf := make([]byte, 64*1024)
	var count int64 = 0
	lineSep := []byte{'\n'}

	for {
		c, err := file.Read(buf)
		count += int64(bytes.Count(buf[:c], lineSep))

		switch {
		case err == io.EOF:
			return count, nil
		case err != nil:
			return nil, zbxerr.New(fmt.Sprintf("Invalid file content")).Wrap(err)
		}
	}
}
