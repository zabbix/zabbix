//go:build linux
// +build linux

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

package procfs

import (
	"bytes"
	"errors"
	"fmt"
	"strconv"
	"strings"
	"syscall"
)

const (
	kB = 1024
	mB = kB * 1024
	gB = mB * 1024
	tB = gB * 1024
)

// GetMemory reads /proc/meminfo file and returns and returns the value in bytes for the
// specific memory type. Returns an error if the value was not found, or if there is an issue
// with reading the file or parsing the value.
func GetMemory(memType string) (mem uint64, err error) {
	meminfo, err := ReadAll("/proc/meminfo")
	if err != nil {
		return mem, fmt.Errorf("cannot read meminfo file: %s", err.Error())
	}

	var found bool
	mem, found, err = ByteFromProcFileData(meminfo, memType)
	if err != nil {
		return mem, fmt.Errorf("cannot get the memory amount for %s: %s", memType, err.Error())
	}

	if !found {
		return mem, fmt.Errorf("cannot get the memory amount for %s", memType)
	}

	return
}

// ReadAll reads all data from a file. Returns an error if there is an issue with reading the file or
// writing the output.
func ReadAll(filename string) (data []byte, err error) {
	fd, err := syscall.Open(filename, syscall.O_RDONLY, 0)
	if err != nil {
		return
	}
	defer syscall.Close(fd)
	var buf bytes.Buffer
	b := make([]byte, 2048)
	for {
		var n int
		if n, err = syscall.Read(fd, b); err != nil {
			if errors.Is(err, syscall.EINTR) {
				continue
			}
			return
		}

		if n == 0 {
			return buf.Bytes(), nil
		}
		if _, err = buf.Write(b[:n]); err != nil {
			return
		}
	}
}

// ByteFromProcFileData returns the value in bytes of the provided value name from the provided
// process file data. Returns true if the value is found, and false if it is not or if there is an
// error. Returns an error if there is an issue with parsing values.
func ByteFromProcFileData(data []byte, valueName string) (uint64, bool, error) {
	for _, line := range strings.Split(string(data), "\n") {
		i := strings.Index(line, ":")
		if i < 0 || valueName != line[:i] {
			continue
		}

		line = line[i+1:]
		if len(line) < 3 {
			continue
		}

		v, err := strconv.ParseUint(strings.TrimSpace(line[:len(line)-2]), 10, 64)
		if err != nil {
			return 0, false, err
		}

		switch line[len(line)-2:] {
		case "kB":
			v *= kB
		case "mB":
			v *= mB
		case "GB":
			v *= gB
		case "TB":
			v *= tB
		default:
			return 0, false, errors.New("cannot resolve value type")
		}
		return v, true, nil
	}

	return 0, false, nil
}
