//go:build !windows
// +build !windows

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

package swap

import (
	"bufio"
	"errors"
	"fmt"
	"io/fs"
	"os"
	"strconv"
	"strings"
	"syscall"

	"git.zabbix.com/ap/plugin-support/plugin"
	"golang.org/x/sys/unix"
)

const (
	devPath          = "/dev/"
	diskstatLocation = "/proc/diskstats"
	swapLocation     = "/proc/swaps"
	vmstatLocation   = "/proc/vmstat"
)

func getSwapSize() (uint64, uint64, error) {
	info := &syscall.Sysinfo_t{}
	if err := syscall.Sysinfo(info); err != nil {
		return 0, 0, err
	}

	return uint64(info.Totalswap), uint64(info.Freeswap), nil
}

func getSwapPages() (r uint64, w uint64, err error) {
	var file *os.File
	var st uint8

	if file, err = os.Open(vmstatLocation); err != nil {
		return
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		var tmp string

		if (1&st) == 0 && strings.HasPrefix(scanner.Text(), "pswpin ") {
			fmt.Sscanf(scanner.Text(), "%s %d", &tmp, &r)
			st |= 1
		} else if (2&st) == 0 && strings.HasPrefix(scanner.Text(), "pswpout ") {
			fmt.Sscanf(scanner.Text(), "%s %d", &tmp, &w)
			st |= 2
		}

		if st == 3 {
			break
		}
	}

	if st != 3 {
		r, w = 0, 0
		err = errors.New("Cannot obtain swap information")
	}

	return
}

func getSwapDevStats(swapdev string, rw bool) (uint64, uint64, error) {
	var err error

	var stat fs.FileInfo
	if stat, err = os.Stat(swapdev); err != nil {
		return 0, 0, err
	}

	var file *os.File
	if file, err = os.Open(diskstatLocation); err != nil {
		return 0, 0, err
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		fields := strings.Fields(scanner.Text())

		var io_idx, sect_idx int
		var io, sect, rev uint64
		var major, minor uint32

		if len(fields) == 7 {
			if rw {
				io_idx, sect_idx = 5, 6
			} else {
				io_idx, sect_idx = 3, 4
			}
		} else if len(fields) >= 10 {
			if rw {
				io_idx, sect_idx = 7, 9
			} else {
				io_idx, sect_idx = 3, 5
			}
		} else {
			continue
		}

		if rev, err = strconv.ParseUint(fields[0], 10, 32); err != nil {
			continue
		}
		major = uint32(rev)

		if rev, err = strconv.ParseUint(fields[1], 10, 32); err != nil {
			continue
		}
		minor = uint32(rev)

		//nolint:unconvert
		rdev := uint64(stat.Sys().(*syscall.Stat_t).Rdev)
		if unix.Major(rdev) != major || unix.Minor(rdev) != minor {
			continue
		}

		if io, err = strconv.ParseUint(fields[io_idx], 10, 64); err != nil {
			continue
		}

		if sect, err = strconv.ParseUint(fields[sect_idx], 10, 64); err != nil {
			continue
		}

		return io, sect, nil
	}

	return 0, 0, errors.New("Cannot obtain swap information")
}

func getSwapStats(swapdev string, rw bool) (io uint64, sect uint64, pag uint64, err error) {
	if len(swapdev) == 0 || swapdev == "all" {
		swapdev = ""
		if rw {
			_, pag, err = getSwapPages()
		} else {
			pag, _, err = getSwapPages()
		}
	} else if !strings.HasPrefix(swapdev, devPath) {
		swapdev += devPath
	}

	var file *os.File
	var errFile error
	if file, errFile = os.Open(swapLocation); errFile != nil {
		return
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		if !strings.HasPrefix(scanner.Text(), devPath) {
			continue
		}

		if len(swapdev) != 0 && !strings.HasPrefix(scanner.Text(), swapdev) {
			continue
		}

		if ioRec, sectRec, errDev := getSwapDevStats(scanner.Text(), rw); errDev == nil {
			io += ioRec
			sect += sectRec

			err = error(nil)
		}
	}

	return
}

func getSwapStatsIn(swapdev string) (uint64, uint64, uint64, error) {
	return getSwapStats(swapdev, false)
}

func getSwapStatsOut(swapdev string) (uint64, uint64, uint64, error) {
	return getSwapStats(swapdev, true)
}

func init() {
	plugin.RegisterMetrics(&impl, "Swap",
		"system.swap.size", "Returns Swap space size in bytes or in percentage from total.",
		"system.swap.in", "Swap in (from device into memory) statistics.",
		"system.swap.out", "Swap out (from memory onto device) statistics.",
	)
}
